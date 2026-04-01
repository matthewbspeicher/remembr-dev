from typing import Optional, Dict, Any
from datetime import datetime
from .client import RemembrClient
from .models import TradeResult

class TradingJournal:
    """Orchestrates two-phase commit trades (Memory then Trade)."""

    def __init__(self, client: RemembrClient, paper: bool = True, default_strategy: Optional[str] = None):
        self.client = client
        self.paper = paper
        self.default_strategy = default_strategy

    def execute_trade(
        self,
        ticker: str,
        direction: str,
        quantity: float,
        price: float,
        reasoning: str,
        strategy: Optional[str] = None
    ) -> TradeResult:
        """
        Execute a trade by first storing a reasoning memory.
        
        Args:
            ticker: Asset ticker (e.g. BTC/USD)
            direction: 'buy' or 'sell'
            quantity: Amount to trade
            price: Execution price
            reasoning: Why this trade is being made
            strategy: Optional strategy name
            
        Returns:
            TradeResult: The created trade record
        """
        # Phase 1: Store memory of type 'trade_decision'
        metadata = {
            "type": "trade_decision",
            "ticker": ticker,
            "direction": direction,
            "strategy": strategy or self.default_strategy
        }
        
        memory_resp = self.client.store(
            value=reasoning,
            metadata=metadata,
            tags=["trade", ticker]
        )
        
        # Extract memory_id
        memory_id = memory_resp.get("id") or memory_resp.get("data", {}).get("id")
        if not memory_id:
            raise Exception("API Error: Failed to retrieve memory_id from store() response")

        # Phase 2: Call /trading/trades
        payload = {
            "ticker": ticker,
            "direction": direction,
            "quantity": quantity,
            "price": price,
            "memory_id": memory_id,
            "paper": self.paper
        }
        
        trade_resp = self.client.post("/trading/trades", json=payload)
        return self._map_to_trade_result(trade_resp)

    def close_trade(
        self,
        parent_trade_id: str,
        price: float,
        reasoning: str,
        quantity: Optional[float] = None
    ) -> TradeResult:
        """
        Close an existing trade by linking an outcome memory.
        
        Args:
            parent_trade_id: ID of the trade being closed
            price: Exit price
            reasoning: Outcome analysis or reason for exit
            quantity: Optional quantity to close (defaults to full remaining if server supports it)
            
        Returns:
            TradeResult: The exit trade record
        """
        # Phase 1: Store outcome memory
        metadata = {
            "type": "trade_outcome",
            "parent_trade_id": parent_trade_id
        }
        
        memory_resp = self.client.store(
            value=reasoning,
            metadata=metadata,
            tags=["trade-exit"]
        )
        
        memory_id = memory_resp.get("id") or memory_resp.get("data", {}).get("id")
        if not memory_id:
            raise Exception("API Error: Failed to retrieve memory_id from store() response")

        # Phase 2: Call /trading/trades for exit
        payload = {
            "parent_trade_id": parent_trade_id,
            "price": price,
            "memory_id": memory_id,
            "paper": self.paper
        }
        if quantity:
            payload["quantity"] = quantity
            
        trade_resp = self.client.post("/trading/trades", json=payload)
        return self._map_to_trade_result(trade_resp)

    def close_all(self, parent_trade_id: str, price: float, reasoning: str) -> TradeResult:
        """
        Convenience method to close the entire remaining quantity of a trade.
        """
        # Fetch parent trade to get current quantity
        trade_data = self.client.get_path(f"/trading/trades/{parent_trade_id}")
        
        # Extract quantity from response
        inner_data = trade_data.get("data") if "data" in trade_data else trade_data
        quantity = inner_data.get("quantity")
        
        if quantity is None:
            raise Exception(f"Could not determine quantity for trade {parent_trade_id}")
            
        return self.close_trade(
            parent_trade_id=parent_trade_id,
            price=price,
            reasoning=reasoning,
            quantity=float(quantity)
        )

    def _map_to_trade_result(self, data: Dict[str, Any]) -> TradeResult:
        """Maps API response dictionary to TradeResult dataclass."""
        item = data.get("data") if "data" in data else data
        
        created_at = item.get("created_at")
        if isinstance(created_at, str):
            try:
                # Basic ISO format handling
                created_at = datetime.fromisoformat(created_at.replace("Z", "+00:00"))
            except ValueError:
                pass
                
        return TradeResult(
            id=item.get("id"),
            parent_trade_id=item.get("parent_trade_id"),
            memory_id=item.get("memory_id"),
            ticker=item.get("ticker"),
            direction=item.get("direction"),
            price=float(item.get("price")),
            quantity=float(item.get("quantity")),
            status=item.get("status"),
            parent_status=item.get("parent_status"),
            pnl=float(item.get("pnl")) if item.get("pnl") is not None else None,
            created_at=created_at
        )
