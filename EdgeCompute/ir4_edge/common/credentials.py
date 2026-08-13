"""Copy EdgeCompute/credentials.md into pole secrets.env files."""

from __future__ import annotations

import os
import re
from pathlib import Path
from typing import Dict, Mapping, MutableMapping, Optional

_EDGE_ROOT = Path(__file__).resolve().parents[2]


def edge_root() -> Path:
    return _EDGE_ROOT


def config_dir() -> Path:
    override = os.environ.get("IR4_EDGE_CONFIG_DIR")
    if override:
        return Path(override)
    return _EDGE_ROOT / "configs"

_ROW = re.compile(
    r"^\|\s*(rfid|gas|cam_ai)\s*\|\s*(DEV-[A-Z0-9-]+)\s*\|\s*"
    r"([0-9a-f-]{36})\s*\|\s*([A-Za-z0-9]+)\s*\|",
    re.IGNORECASE,
)

_ENV_ORDER = (
    "IR4_BASE_URL",
    "APP_TIMEZONE",
    "IR4_GAS_DEVICE_REF",
    "IR4_GAS_DEVICE_TOKEN",
    "IR4_GAS_DEVICE_UUID",
    "IR4_RFID_READER_REF",
    "IR4_RFID_MQTT_TOPIC",
    "IR4_RFID_DEVICE_TOKEN",
    "IR4_RFID_DEVICE_UUID",
    "IR4_MQTT_USE_AUTH",
    "IR4_MQTT_USERNAME",
    "IR4_MQTT_PASSWORD",
    "IR4_MQTT_FXR90_PASSWORD",
    "IR4_DRY_RUN",
)


def credentials_path() -> Path:
    return edge_root() / "credentials.md"


def load_credentials(path: Optional[Path] = None) -> Dict[str, Dict[str, str]]:
    file = path or credentials_path()
    rows: Dict[str, Dict[str, str]] = {}
    for line in file.read_text(encoding="utf-8").splitlines():
        match = _ROW.match(line)
        if match is None:
            continue
        rows[match.group(2)] = {
            "type": match.group(1).lower(),
            "ref": match.group(2),
            "uuid": match.group(3).lower(),
            "token": match.group(4),
        }
    if not rows:
        raise RuntimeError("No device rows parsed from {}".format(file))
    return rows


def read_env(path: Path) -> Dict[str, str]:
    values: Dict[str, str] = {}
    if not path.is_file():
        return values
    for raw in path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        values[key.strip()] = value.strip().strip("'").strip('"')
    return values


def write_env(path: Path, values: Mapping[str, str], header: str) -> None:
    lines = [line.rstrip() for line in header.strip().splitlines()]
    lines.append("")
    seen = set()
    for key in _ENV_ORDER:
        if key not in values:
            continue
        lines.append("{}={}".format(key, values[key]))
        seen.add(key)
        if key in {"APP_TIMEZONE", "IR4_GAS_DEVICE_UUID", "IR4_RFID_DEVICE_UUID"}:
            lines.append("")
    for key in sorted(values):
        if key in seen:
            continue
        lines.append("{}={}".format(key, values[key]))
    path.write_text("\n".join(lines).rstrip() + "\n", encoding="utf-8")


def apply_credentials_to_values(
    pole: int,
    existing: MutableMapping[str, str],
    creds: Optional[Mapping[str, Dict[str, str]]] = None,
) -> Dict[str, str]:
    if pole < 1 or pole > 4:
        raise ValueError("pole must be 1–4")
    pad = "{:02d}".format(pole)
    table = creds or load_credentials()
    gas = table.get("DEV-GAS-{}".format(pad))
    rfid = table.get("DEV-RFID-{}".format(pad))
    if gas is None or rfid is None:
        raise RuntimeError("credentials.md missing DEV-GAS-{0} / DEV-RFID-{0}".format(pad))
    merged = dict(existing)
    merged.setdefault("IR4_BASE_URL", "http://192.168.8.40:9100")
    merged.setdefault("APP_TIMEZONE", "Asia/Riyadh")
    merged["IR4_GAS_DEVICE_REF"] = gas["ref"]
    merged["IR4_GAS_DEVICE_TOKEN"] = gas["token"]
    merged["IR4_GAS_DEVICE_UUID"] = gas["uuid"]
    merged["IR4_RFID_READER_REF"] = rfid["ref"]
    merged["IR4_RFID_MQTT_TOPIC"] = merged.get("IR4_RFID_MQTT_TOPIC") or "zebra/fxr90-{}/tags".format(pad)
    merged["IR4_RFID_DEVICE_TOKEN"] = rfid["token"]
    merged["IR4_RFID_DEVICE_UUID"] = rfid["uuid"]
    merged.setdefault("IR4_MQTT_USE_AUTH", "0")
    merged.setdefault("IR4_MQTT_USERNAME", "ir4-rfid")
    return merged


def pole_secrets_path(pole: int) -> Path:
    return config_dir() / "secrets.pole-{:02d}.env".format(pole)


def apply_pole_secrets(pole: int, dest: Optional[Path] = None) -> Path:
    """Write credentials.md gas/RFID rows into secrets.env (and keep MQTT from the pole file)."""
    pad = "{:02d}".format(pole)
    pole_file = pole_secrets_path(pole)
    target = dest or (config_dir() / "secrets.env")
    existing = read_env(pole_file)
    existing.update(read_env(target))
    values = apply_credentials_to_values(pole, existing)
    header = "# Pole {} — copied from credentials.md (DEV-GAS-{} + DEV-RFID-{})".format(pad, pad, pad)
    write_env(target, values, header)
    return target


def refresh_committed_pole_files() -> None:
    creds = load_credentials()
    for pole in (1, 2, 3, 4):
        path = pole_secrets_path(pole)
        values = apply_credentials_to_values(pole, read_env(path), creds)
        pad = "{:02d}".format(pole)
        header = (
            "# Pole {} — ir4-edge secrets --pole {}\n"
            "# Hostname pole-{} · DEV-GAS-{} + DEV-RFID-{}\n"
            "# Tokens copied from credentials.md"
        ).format(pad, pole, pad, pad, pad)
        write_env(path, values, header)
