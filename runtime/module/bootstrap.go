package showcasebootstrap

import (
	"crypto/rand"
	"encoding/base64"
	"fmt"
	"log/slog"
	"net/url"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"syscall"
	"time"

	"github.com/caddyserver/caddy/v2"
	"github.com/caddyserver/caddy/v2/caddyconfig"
	"github.com/caddyserver/caddy/v2/caddyconfig/caddyfile"
	"github.com/caddyserver/caddy/v2/caddyconfig/httpcaddyfile"
)

const appName = "aaa_pogo_showcase_bootstrap"

func init() {
	caddy.RegisterModule(Bootstrap{})
	httpcaddyfile.RegisterGlobalOption("pogo_showcase_bootstrap", parseGlobalOption)
}

type Bootstrap struct {
	DataDir       string `json:"data_dir,omitempty"`
	SkipMigrate   bool   `json:"skip_migrate,omitempty"`
	DefaultDomain string `json:"default_domain,omitempty"`

	logger *slog.Logger
}

func (Bootstrap) CaddyModule() caddy.ModuleInfo {
	return caddy.ModuleInfo{
		ID:  appName,
		New: func() caddy.Module { return new(Bootstrap) },
	}
}

func (b *Bootstrap) Provision(ctx caddy.Context) error {
	b.logger = ctx.Slogger()
	return b.bootstrap()
}

func (b *Bootstrap) Start() error {
	return nil
}

func (b *Bootstrap) Stop() error {
	return nil
}

func (b *Bootstrap) bootstrap() error {
	dataDir, err := b.resolveDataDir()
	if err != nil {
		return err
	}

	if err := mkdirs(dataDir); err != nil {
		return err
	}

	envPath := filepath.Join(dataDir, "runtime.env")
	values, err := readEnvFile(envPath)
	if err != nil {
		return err
	}

	if _, ok := values["APP_KEY"]; !ok {
		values["APP_KEY"] = "base64:" + randomBase64(32)
	}
	if _, ok := values["WS_APP_SECRET"]; !ok {
		values["WS_APP_SECRET"] = randomBase64(32)
	}
	if _, ok := values["POGO_WEBHOOK_SECRET"]; !ok {
		values["POGO_WEBHOOK_SECRET"] = randomBase64(32)
	}

	if err := writeEnvFile(envPath, values); err != nil {
		return err
	}

	dbPath := filepath.Join(dataDir, "database.sqlite")
	defaults := map[string]string{
		"APP_ENV":              "production",
		"APP_DEBUG":            "false",
		"APP_KEY":              values["APP_KEY"],
		"APP_NAME":             "Pogo Showcase",
		"APP_URL":              b.appURL(),
		"DB_CONNECTION":        "sqlite",
		"DB_DATABASE":          dbPath,
		"QUEUE_CONNECTION":     "pogo",
		"POGO_QUEUE":           "default",
		"BROADCAST_CONNECTION": "pogo",
		"WS_APP_ID":            "pogo-app",
		"WS_APP_SECRET":        values["WS_APP_SECRET"],
		"POGO_WS_APP_SECRET":   values["WS_APP_SECRET"],
		"POGO_WEBHOOK_SECRET":  values["POGO_WEBHOOK_SECRET"],
		"CACHE_STORE":          "database",
		"SESSION_DRIVER":       "database",
		"SESSION_ENCRYPT":      "true",
		"LOG_CHANNEL":          "single",
		"LOG_LEVEL":            "info",
		"LARAVEL_STORAGE_PATH": filepath.Join(dataDir, "storage"),
		"POGO_SHOWCASE_DATA":   dataDir,
	}

	for key, value := range defaults {
		if os.Getenv(key) == "" {
			if err := os.Setenv(key, value); err != nil {
				return fmt.Errorf("set %s: %w", key, err)
			}
		}
	}

	if _, err := os.Stat(dbPath); err != nil {
		if !os.IsNotExist(err) {
			return err
		}
		file, err := os.OpenFile(dbPath, os.O_RDONLY|os.O_CREATE, 0o600)
		if err != nil {
			return fmt.Errorf("create sqlite database: %w", err)
		}
		_ = file.Close()
	}

	if b.SkipMigrate || envBool("POGO_SHOWCASE_SKIP_MIGRATE") {
		return nil
	}

	return b.migrate(dataDir)
}

func (b *Bootstrap) migrate(dataDir string) error {
	lockPath := filepath.Join(dataDir, "bootstrap.lock")
	lockFile, err := os.OpenFile(lockPath, os.O_CREATE|os.O_RDWR, 0o600)
	if err != nil {
		return fmt.Errorf("open bootstrap lock: %w", err)
	}
	defer lockFile.Close()

	if err := syscall.Flock(int(lockFile.Fd()), syscall.LOCK_EX); err != nil {
		return fmt.Errorf("lock bootstrap: %w", err)
	}
	defer syscall.Flock(int(lockFile.Fd()), syscall.LOCK_UN)

	exe, err := os.Executable()
	if err != nil {
		return err
	}

	cmd := exec.Command(exe, "php-cli", "artisan", "migrate", "--force")
	cmd.Stdout = os.Stdout
	cmd.Stderr = os.Stderr
	cmd.Env = os.Environ()
	cmd.Dir = "."

	start := time.Now()
	if b.logger != nil {
		b.logger.Info("running pogo showcase migrations", slog.String("data_dir", dataDir))
	}
	if err := cmd.Run(); err != nil {
		return fmt.Errorf("run migrations: %w", err)
	}
	if b.logger != nil {
		b.logger.Info("pogo showcase migrations complete", slog.Duration("duration", time.Since(start)))
	}

	return nil
}

func (b *Bootstrap) resolveDataDir() (string, error) {
	if b.DataDir == "" {
		b.DataDir = os.Getenv("POGO_SHOWCASE_DATA")
	}
	if b.DataDir == "" {
		exe, err := os.Executable()
		if err != nil {
			return "", err
		}
		exe, err = filepath.EvalSymlinks(exe)
		if err != nil {
			return "", err
		}
		b.DataDir = filepath.Join(filepath.Dir(exe), "data")
	}

	abs, err := filepath.Abs(b.DataDir)
	if err != nil {
		return "", err
	}

	return abs, nil
}

func (b *Bootstrap) appURL() string {
	if value := os.Getenv("APP_URL"); value != "" {
		return value
	}
	name := os.Getenv("SERVER_NAME")
	if name == "" {
		name = b.DefaultDomain
	}
	if name == "" || name == "localhost" {
		return "http://localhost"
	}
	first := strings.TrimSpace(strings.Split(name, ",")[0])
	if strings.HasPrefix(first, "http://") || strings.HasPrefix(first, "https://") {
		if parsed, err := url.Parse(first); err == nil && parsed.Host != "" {
			return strings.TrimRight(first, "/")
		}
	}
	if strings.HasPrefix(first, ":") {
		return "http://localhost" + first
	}
	return "https://" + first
}

func mkdirs(dataDir string) error {
	dirs := []string{
		dataDir,
		filepath.Join(dataDir, "storage", "app", "private"),
		filepath.Join(dataDir, "storage", "app", "public"),
		filepath.Join(dataDir, "storage", "framework", "cache", "data"),
		filepath.Join(dataDir, "storage", "framework", "sessions"),
		filepath.Join(dataDir, "storage", "framework", "testing"),
		filepath.Join(dataDir, "storage", "framework", "views"),
		filepath.Join(dataDir, "storage", "logs"),
	}
	for _, dir := range dirs {
		if err := os.MkdirAll(dir, 0o755); err != nil {
			return fmt.Errorf("create %s: %w", dir, err)
		}
	}
	return nil
}

func readEnvFile(path string) (map[string]string, error) {
	values := map[string]string{}
	body, err := os.ReadFile(path)
	if err != nil {
		if os.IsNotExist(err) {
			return values, nil
		}
		return nil, err
	}
	for _, line := range strings.Split(string(body), "\n") {
		line = strings.TrimSpace(line)
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}
		key, value, ok := strings.Cut(line, "=")
		if !ok {
			continue
		}
		values[strings.TrimSpace(key)] = strings.Trim(strings.TrimSpace(value), `"`)
	}
	return values, nil
}

func writeEnvFile(path string, values map[string]string) error {
	keys := []string{"APP_KEY", "WS_APP_SECRET", "POGO_WEBHOOK_SECRET"}
	var out strings.Builder
	out.WriteString("# Generated by pogo-showcase. Keep this file to preserve sessions and websocket auth.\n")
	for _, key := range keys {
		out.WriteString(key)
		out.WriteString("=")
		out.WriteString(values[key])
		out.WriteString("\n")
	}
	return os.WriteFile(path, []byte(out.String()), 0o600)
}

func randomBase64(size int) string {
	buf := make([]byte, size)
	if _, err := rand.Read(buf); err != nil {
		panic(err)
	}
	return base64.StdEncoding.EncodeToString(buf)
}

func envBool(key string) bool {
	switch strings.ToLower(os.Getenv(key)) {
	case "1", "true", "yes", "on":
		return true
	default:
		return false
	}
}

func (b *Bootstrap) UnmarshalCaddyfile(d *caddyfile.Dispenser) error {
	for d.Next() {
		for d.NextBlock(0) {
			switch d.Val() {
			case "data_dir":
				if !d.NextArg() {
					return d.ArgErr()
				}
				b.DataDir = d.Val()
			case "skip_migrate":
				if !d.NextArg() {
					b.SkipMigrate = true
					continue
				}
				value := strings.ToLower(d.Val())
				b.SkipMigrate = value == "1" || value == "true" || value == "yes" || value == "on"
			case "default_domain":
				if !d.NextArg() {
					return d.ArgErr()
				}
				b.DefaultDomain = d.Val()
			default:
				return d.Errf("unrecognized subdirective %q", d.Val())
			}
		}
	}
	return nil
}

func parseGlobalOption(d *caddyfile.Dispenser, _ any) (any, error) {
	app := &Bootstrap{}
	if err := app.UnmarshalCaddyfile(d); err != nil {
		return nil, err
	}

	return httpcaddyfile.App{
		Name:  appName,
		Value: caddyconfig.JSON(app, nil),
	}, nil
}

var (
	_ caddy.App             = (*Bootstrap)(nil)
	_ caddy.Module          = (*Bootstrap)(nil)
	_ caddy.Provisioner     = (*Bootstrap)(nil)
	_ caddyfile.Unmarshaler = (*Bootstrap)(nil)
)
