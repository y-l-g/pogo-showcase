package showcasebootstrap

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestPrepareEnvironmentLoadsAndPreservesRuntimeEnv(t *testing.T) {
	preserveEnv(t,
		"APP_ENV",
		"APP_DEBUG",
		"APP_KEY",
		"APP_NAME",
		"APP_URL",
		"DB_CONNECTION",
		"DB_DATABASE",
		"QUEUE_CONNECTION",
		"POGO_QUEUE",
		"BROADCAST_CONNECTION",
		"REVERB_APP_ID",
		"REVERB_APP_KEY",
		"REVERB_APP_SECRET",
		"REVERB_HOST",
		"REVERB_PORT",
		"REVERB_SCHEME",
		"WS_APP_ID",
		"WS_APP_SECRET",
		"POGO_WEBHOOK_SECRET",
		"POGO_UPLOAD_SECRET",
		"POGO_UPLOAD_ROOT",
		"CACHE_STORE",
		"SESSION_DRIVER",
		"SESSION_ENCRYPT",
		"LOG_CHANNEL",
		"LOG_LEVEL",
		"PHP_BINARY",
		"LARAVEL_STORAGE_PATH",
		"POGO_SHOWCASE_DATA",
		"MAIL_MAILER",
		"MAIL_PASSWORD",
		"MAIL_FROM_NAME",
	)

	dataDir := t.TempDir()
	envPath := filepath.Join(dataDir, "runtime.env")
	err := os.WriteFile(envPath, []byte(strings.Join([]string{
		"APP_KEY=base64:test-app-key",
		"WS_APP_SECRET=test-ws-secret",
		"POGO_WEBHOOK_SECRET=test-webhook-secret",
		"POGO_UPLOAD_SECRET=test-upload-secret",
		"MAIL_MAILER=smtp",
		"MAIL_PASSWORD=from-file",
		"MAIL_FROM_NAME=\"Pogo Showcase\"",
		"",
	}, "\n")), 0o600)
	if err != nil {
		t.Fatal(err)
	}

	if err := os.Setenv("MAIL_PASSWORD", "from-process"); err != nil {
		t.Fatal(err)
	}

	_, err = (&Bootstrap{DataDir: dataDir}).prepareEnvironment()
	if err != nil {
		t.Fatal(err)
	}

	if got := os.Getenv("MAIL_MAILER"); got != "smtp" {
		t.Fatalf("MAIL_MAILER = %q, want smtp", got)
	}
	if got := os.Getenv("MAIL_PASSWORD"); got != "from-process" {
		t.Fatalf("MAIL_PASSWORD = %q, want process env override", got)
	}
	if got := os.Getenv("MAIL_FROM_NAME"); got != "Pogo Showcase" {
		t.Fatalf("MAIL_FROM_NAME = %q, want parsed quoted value", got)
	}

	body, err := os.ReadFile(envPath)
	if err != nil {
		t.Fatal(err)
	}
	text := string(body)
	for _, want := range []string{
		"APP_KEY=base64:test-app-key\n",
		"REVERB_APP_SECRET=test-ws-secret\n",
		"WS_APP_SECRET=test-ws-secret\n",
		"POGO_WEBHOOK_SECRET=test-webhook-secret\n",
		"POGO_UPLOAD_SECRET=test-upload-secret\n",
		"MAIL_MAILER=smtp\n",
		"MAIL_PASSWORD=from-file\n",
		"MAIL_FROM_NAME=Pogo Showcase\n",
	} {
		if !strings.Contains(text, want) {
			t.Fatalf("runtime.env missing %q in:\n%s", want, text)
		}
	}

	info, err := os.Stat(envPath)
	if err != nil {
		t.Fatal(err)
	}
	if got := info.Mode().Perm(); got != 0o600 {
		t.Fatalf("runtime.env mode = %v, want 0600", got)
	}
}

func TestPrepareEnvironmentGeneratesSecretsWithoutDroppingOperatorValues(t *testing.T) {
	preserveEnv(t,
		"APP_ENV",
		"APP_DEBUG",
		"APP_KEY",
		"APP_NAME",
		"APP_URL",
		"DB_CONNECTION",
		"DB_DATABASE",
		"QUEUE_CONNECTION",
		"POGO_QUEUE",
		"BROADCAST_CONNECTION",
		"REVERB_APP_ID",
		"REVERB_APP_KEY",
		"REVERB_APP_SECRET",
		"REVERB_HOST",
		"REVERB_PORT",
		"REVERB_SCHEME",
		"WS_APP_ID",
		"WS_APP_SECRET",
		"POGO_WEBHOOK_SECRET",
		"POGO_UPLOAD_SECRET",
		"POGO_UPLOAD_ROOT",
		"CACHE_STORE",
		"SESSION_DRIVER",
		"SESSION_ENCRYPT",
		"LOG_CHANNEL",
		"LOG_LEVEL",
		"PHP_BINARY",
		"LARAVEL_STORAGE_PATH",
		"POGO_SHOWCASE_DATA",
		"MAIL_MAILER",
	)

	dataDir := t.TempDir()
	envPath := filepath.Join(dataDir, "runtime.env")
	if err := os.WriteFile(envPath, []byte("MAIL_MAILER=smtp\n"), 0o600); err != nil {
		t.Fatal(err)
	}

	_, err := (&Bootstrap{DataDir: dataDir}).prepareEnvironment()
	if err != nil {
		t.Fatal(err)
	}

	values, err := readEnvFile(envPath)
	if err != nil {
		t.Fatal(err)
	}

	if got := values["MAIL_MAILER"]; got != "smtp" {
		t.Fatalf("MAIL_MAILER = %q, want smtp", got)
	}
	if got := values["APP_KEY"]; !strings.HasPrefix(got, "base64:") {
		t.Fatalf("APP_KEY = %q, want generated base64 key", got)
	}
	if values["REVERB_APP_SECRET"] == "" {
		t.Fatal("REVERB_APP_SECRET was not generated")
	}
	if values["POGO_WEBHOOK_SECRET"] == "" {
		t.Fatal("POGO_WEBHOOK_SECRET was not generated")
	}
	if values["POGO_UPLOAD_SECRET"] == "" {
		t.Fatal("POGO_UPLOAD_SECRET was not generated")
	}
	if got := os.Getenv("POGO_UPLOAD_ROOT"); got != filepath.Join(dataDir, "storage", "app", "pogo-uploads") {
		t.Fatalf("POGO_UPLOAD_ROOT = %q, want data dir upload root", got)
	}
}

func preserveEnv(t *testing.T, keys ...string) {
	t.Helper()

	type entry struct {
		key    string
		value  string
		exists bool
	}

	snapshot := make([]entry, 0, len(keys))
	for _, key := range keys {
		value, exists := os.LookupEnv(key)
		snapshot = append(snapshot, entry{key: key, value: value, exists: exists})
		if err := os.Unsetenv(key); err != nil {
			t.Fatal(err)
		}
	}

	t.Cleanup(func() {
		for _, item := range snapshot {
			if item.exists {
				_ = os.Setenv(item.key, item.value)
				continue
			}
			_ = os.Unsetenv(item.key)
		}
	})
}
