"""Transport layer — how code reaches the pole."""

from __future__ import annotations

from abc import ABC, abstractmethod

from ir4_edge.deploy.models import CodeArtifact, DeployContext


class Transport(ABC):
    """Deliver a code tree for overlay onto /opt/ir4-edge/EdgeCompute."""

    name: str

    @abstractmethod
    def deliver(self, ctx: DeployContext) -> CodeArtifact:
        """Fetch or locate code; may use network (direct) or local payload (SCC)."""
