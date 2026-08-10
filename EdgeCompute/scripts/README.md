# Scripts

| Script | Purpose |
|---|---|
| [`configure.sh`](configure.sh) | Interactive tokens / refs → `configs/secrets.local.env` |
| [`validate_api_contract.py`](validate_api_contract.py) | Static (+ optional `--live`) Edge ↔ Server API check |
| [`smoke_gas_dry_run.py`](smoke_gas_dry_run.py) | Print one gas ingest payload |
| [`smoke_rfid_dry_run.py`](smoke_rfid_dry_run.py) | Print one RFID ingest payload |

```bash
./scripts/configure.sh
.venv/bin/python scripts/validate_api_contract.py
.venv/bin/python scripts/validate_api_contract.py --live
ir4-gas-agent --dry-run
```

Host bootstrap: [`../deploy/orin_bootstrap.sh`](../deploy/orin_bootstrap.sh).
