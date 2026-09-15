#!/www/server/panel/pyenv/bin/python

"""One-time aaPanel provisioning for the AquaDissect production site."""

from __future__ import annotations

import base64
import grp
import json
import os
from pathlib import Path
import secrets
import subprocess
import sys


PANEL_ROOT = Path("/www/server/panel")
SITE_ROOT = Path("/www/wwwroot/aquadissect.com")
PUBLIC_ROOT = SITE_ROOT / "public"
ENV_PATH = SITE_ROOT / ".env"
USER_INI_PATH = PUBLIC_ROOT / ".user.ini"
INITIAL_ADMIN_PATH = Path("/root/aquadissect-initial-admin.txt")

sys.path.insert(0, str(PANEL_ROOT / "class"))
sys.path.insert(0, str(PANEL_ROOT))

import public  # noqa: E402
from panelSite import panelSite  # noqa: E402


def main() -> None:
    if ENV_PATH.exists():
        raise SystemExit(f"Refusing to overwrite existing production configuration: {ENV_PATH}")

    database_name = "aquadissect_db"
    database_user = "aquadissect_db"
    database_password = secrets.token_urlsafe(32)
    app_key = "base64:" + base64.b64encode(secrets.token_bytes(32)).decode("ascii")
    initial_admin_password = secrets.token_urlsafe(18)

    args = public.to_dict_obj(
        {
            "webname": json.dumps(
                {
                    "domain": "aquadissect.com",
                    "domainlist": ["www.aquadissect.com"],
                    "count": 0,
                }
            ),
            "ftp": "false",
            "type": "PHP",
            "project_type": "PHP",
            "type_id": 0,
            "version": "83",
            "port": "80",
            "path": str(PUBLIC_ROOT),
            "sql": "MySQL",
            "datauser": database_user,
            "datapassword": database_password,
            "codeing": "utf8mb4",
            "ps": "AquaDissect production",
        }
    )

    result = panelSite().AddSite(args)
    if not result.get("siteStatus") or not result.get("databaseStatus"):
        raise RuntimeError(f"aaPanel provisioning failed: {result}")

    clone_path = SITE_ROOT / "storage/app/private/aquadissect-clone.sqlite"
    env_lines = [
        "APP_NAME=AquaDissect",
        "APP_ENV=production",
        f"APP_KEY={app_key}",
        "APP_DEBUG=false",
        "APP_URL=https://aquadissect.com",
        "ASSET_URL=",
        "APP_LOCALE=en",
        "APP_FALLBACK_LOCALE=en",
        "APP_FAKER_LOCALE=en_US",
        "APP_MAINTENANCE_DRIVER=file",
        "APP_AUTO_MIGRATE=false",
        "BCRYPT_ROUNDS=12",
        "LOG_CHANNEL=stack",
        "LOG_STACK=single",
        "LOG_LEVEL=warning",
        "DB_CONNECTION=mysql",
        "DB_HOST=127.0.0.1",
        "DB_PORT=3306",
        f"DB_DATABASE={database_name}",
        f"DB_USERNAME={database_user}",
        f"DB_PASSWORD={database_password}",
        "CLONE_ENABLED=true",
        "CLONE_DB_CONNECTION=sqlite_clone",
        f"CLONE_DB_DATABASE={clone_path}",
        "CLONE_DELAY_HOURS=12",
        "CLONE_BATCH_SIZE=500",
        "SESSION_DRIVER=database",
        "SESSION_LIFETIME=120",
        "SESSION_ENCRYPT=true",
        "SESSION_PATH=/",
        "SESSION_DOMAIN=.aquadissect.com",
        "BROADCAST_CONNECTION=log",
        "FILESYSTEM_DISK=local",
        "QUEUE_CONNECTION=database",
        "CACHE_STORE=database",
        "MAIL_MAILER=log",
        "MAIL_FROM_ADDRESS=noreply@aquadissect.com",
        'MAIL_FROM_NAME="${APP_NAME}"',
        'VITE_APP_NAME="${APP_NAME}"',
        "",
    ]

    SITE_ROOT.mkdir(parents=True, exist_ok=True)
    ENV_PATH.write_text("\n".join(env_lines), encoding="utf-8", newline="\n")
    os.chown(ENV_PATH, 0, grp.getgrnam("www").gr_gid)
    os.chmod(ENV_PATH, 0o640)

    # Laravel's front controller must be able to load application files above
    # the public document root. aaPanel makes this file immutable by default.
    subprocess.run(["chattr", "-i", str(USER_INI_PATH)], check=False)
    USER_INI_PATH.write_text(
        f"open_basedir={SITE_ROOT}/:/tmp/\n",
        encoding="utf-8",
        newline="\n",
    )
    os.chown(USER_INI_PATH, 0, grp.getgrnam("www").gr_gid)
    os.chmod(USER_INI_PATH, 0o644)
    subprocess.run(["chattr", "+i", str(USER_INI_PATH)], check=True)

    INITIAL_ADMIN_PATH.write_text(
        "admin@aquadissect.com\n" + initial_admin_password + "\n",
        encoding="utf-8",
        newline="\n",
    )
    os.chmod(INITIAL_ADMIN_PATH, 0o600)

    print(
        json.dumps(
            {
                "site_id": result.get("siteId"),
                "site": "aquadissect.com",
                "database": database_name,
                "php": "8.3",
                "credentials_file": str(INITIAL_ADMIN_PATH),
            }
        )
    )


if __name__ == "__main__":
    main()
