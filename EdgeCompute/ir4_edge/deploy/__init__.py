"""Pole installation and update — shared logic, pluggable transport.

Architecture::

    Controller  →  DeployService  →  DeployPipeline  →  Transport
                                                          ├─ Direct (pole + internet)
                                                          └─ SCC (SCC → pole package)

Host operations (venv, systemd, Mosquitto) run via ``deploy/host.sh`` so the
Python layer stays testable without root.
"""

from ir4_edge.deploy.models import DeployStatus, OperationKind, OperationRecord
from ir4_edge.deploy.service import DeployService

__all__ = [
    "DeployService",
    "DeployStatus",
    "OperationKind",
    "OperationRecord",
]
