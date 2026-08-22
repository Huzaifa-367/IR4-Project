"""Deploy domain types — operation state, results, and code artifacts."""

from __future__ import annotations

from dataclasses import dataclass, field
from enum import Enum
from pathlib import Path
from typing import Any, Dict, List, Optional


class OperationKind(str, Enum):
    INSTALL = "install"
    UPDATE = "update"


class DeployStatus(str, Enum):
    """Persisted operation lifecycle."""

    PENDING = "pending"
    DELIVERING = "delivering"
    HOST_SETUP = "host_setup"
    CONFIGURING = "configuring"
    VERIFYING = "verifying"
    SUCCESS = "success"
    FAILED = "failed"
    INTERRUPTED = "interrupted"


class TransportName(str, Enum):
    DIRECT = "direct"
    SCC = "scc"


@dataclass(frozen=True)
class CodeArtifact:
    """Code tree delivered by a transport before overlay onto /opt."""

    source_root: Path
    version: str
    transport: TransportName


@dataclass
class DeployContext:
    """Inputs for one install or update run on a pole."""

    pole: int
    kind: OperationKind
    transport: TransportName
    install_root: Path = Path("/opt/ir4-edge")
    branch: str = "main"
    repo_url: str = "https://github.com/Huzaifa-367/IR4-Project.git"
    payload_dir: Optional[Path] = None
    from_path: Optional[Path] = None
    operation_id: str = ""
    force: bool = False


@dataclass
class OperationRecord:
    id: str
    kind: OperationKind
    transport: TransportName
    pole: int
    target_version: str
    status: DeployStatus
    message: str = ""
    details: Dict[str, Any] = field(default_factory=dict)


@dataclass
class DeployResult:
    ok: bool
    status: DeployStatus
    operation_id: str
    target_version: str = ""
    deployed_version: str = ""
    message: str = ""
    verification_failures: List[str] = field(default_factory=list)
    already_current: bool = False
