from typing import Union, List, Dict, Any, Optional

from .client import RemembrClient, AsyncRemembrClient

class TurboContextLoader:
    """
    Loader for TurboQuant KV Cache optimization.
    March 2026 Reference: TurboQuant v2.4+ implements "compress_to_cache"
    for dynamic KV cache pre-loading.
    """

    def __init__(self, client: Union[RemembrClient, AsyncRemembrClient]):
        self.client = client

    def load_context(self, q: str, model: Any, limit: int = 50) -> Any:
        """
        Search for memories and compress them into a KV cache for the target model.
        Returns the TurboQuant cache object or raises ImportError if turboquant is missing.
        """
        try:
            import turboquant
        except ImportError:
            raise ImportError(
                "turboquant is not installed. To use TurboContextLoader, "
                "install it via 'pip install turboquant>=2.4.0'"
            )

        if isinstance(self.client, AsyncRemembrClient):
            raise TypeError("AsyncRemembrClient detected. Please use 'await loader.aload_context(...)'")

        memories = self.client.search(q, limit=limit)
        context_text = "\n".join([m.get("value", "") for m in memories])
        
        model_id = model if isinstance(model, str) else getattr(model, "model_id", str(model))
        
        return turboquant.compress_to_cache(context_text, model_id=model_id)

    async def aload_context(self, q: str, model: Any, limit: int = 50) -> Any:
        """
        Asynchronous version of load_context.
        """
        try:
            import turboquant
        except ImportError:
            raise ImportError(
                "turboquant is not installed. To use TurboContextLoader, "
                "install it via 'pip install turboquant>=2.4.0'"
            )

        if not isinstance(self.client, AsyncRemembrClient):
            raise TypeError("aload_context requires an AsyncRemembrClient")

        memories = await self.client.search(q, limit=limit)
        context_text = "\n".join([m.get("value", "") for m in memories])
        
        model_id = model if isinstance(model, str) else getattr(model, "model_id", str(model))
        
        return turboquant.compress_to_cache(context_text, model_id=model_id)
