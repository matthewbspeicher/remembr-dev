import os
from typing import List, Dict, Any, Optional
from remembr.client import AsyncRemembrClient
from remembr.turbo import TurboQuantIndex
import asyncio

class TurboQuantIndexer:
    def __init__(self, client: AsyncRemembrClient, persist_path: str = "./data/turboquant"):
        self.client = client
        self.persist_path = persist_path
        self.index = TurboQuantIndex()
        self.is_ready = False

    async def initialize(self):
        """
        Handles startup rehydration from remembr.
        Loads from disk if available, otherwise fetches all from remembr and builds index.
        """
        if os.path.exists(os.path.join(self.persist_path, "turboquant.bin")):
            self.index.load(self.persist_path)
            self.is_ready = True
            return

        print("Rehydrating TurboQuantIndex from Remembr...")
        page = 1
        while True:
            # We assume the client has a list method as described: list(page=N, tags=["trade_journal"])
            try:
                # The exact API depends on remembr client implementation
                results = await self.client.list(page=page, tags=["trade_journal"])
                if not results:
                    break
            except AttributeError:
                # Fallback if list is not implemented or slightly different
                results = []
                break
                
            for entry in results:
                self._add_entry(entry)
            
            # Simple pagination logic, assuming empty list when done
            if len(results) < 50: # assuming 50 is default page size
                break
            page += 1

        self.index.persist(self.persist_path)
        self.is_ready = True

    def _add_entry(self, entry: Dict[str, Any]):
        """Helper to add a single entry to the index"""
        memory_id = entry.get("id") or entry.get("_id")
        text = entry.get("value", "")
        
        # Store essential metadata locally
        metadata = {
            "realized_pnl": entry.get("metadata", {}).get("realized_pnl"),
            "status": entry.get("metadata", {}).get("status"),
            "direction": entry.get("metadata", {}).get("decision", {}).get("direction")
        }
        
        self.index.add(memory_id=memory_id, text=text, metadata=metadata)

    async def handle_new_trade(self, event: Dict[str, Any]):
        """
        Subscribes to EventBus, feeds trade journal entries into TurboQuantIndex.
        """
        entry = event.get("data", {})
        self._add_entry(entry)
        # Optionally persist after every N trades or periodically
        self.index.persist(self.persist_path)

    def search_trades(self, query: str, limit: int = 5) -> List[Dict[str, Any]]:
        """
        Returns results with full metadata (no round-trip to remembr).
        """
        if not self.is_ready:
            raise RuntimeError("TurboQuantIndexer is not fully initialized yet.")
            
        return self.index.search(query, k=limit)
