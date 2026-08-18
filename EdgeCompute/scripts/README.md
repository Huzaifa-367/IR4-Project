# Scripts

Install and update: [../deploy/README.md](../deploy/README.md).

Day-2 on a pole that is already installed:

| Command | What |
|---|---|
| `ir4-edge doctor` | Health check |
| `ir4-edge status` / `restart` / `logs -f` | Agents |
| `ir4-edge secrets --pole N` | Rewrite this pole’s URL + tokens |
| `ir4-edge up` / `down` | Enable/disable agents in `edge.yaml` |

Optional: `validate_api_contract.py`, `smoke_gas_dry_run.py`, `smoke_rfid_dry_run.py`
