import asyncio
import json
import logging
from typing import Dict, List, Optional, Tuple
from bisect import bisect_left, insort

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("OrderbookCache")

class Orderbook:
    """
    In-memory L2 Orderbook for a single market.
    Maintains sorted bids and asks for $O(\log N)$ updates and $O(1)$ top-of-book access.
    """
    def __init__(self, ticker: str):
        self.ticker = ticker
        self.bids: List[List[float]] = [] # [[price, size], ...] sorted by price DESC
        self.asks: List[List[float]] = [] # [[price, size], ...] sorted by price ASC
        self.last_update_id = 0

    def update(self, side: str, price: float, size: float):
        """Update or remove a price level."""
        book = self.bids if side == 'buy' else self.asks
        prices = [level[0] for level in book]
        
        # Binary search for the price level
        idx = bisect_left(prices, price) if side == 'sell' else bisect_left([-p for p in prices], -price)
        
        if idx < len(book) and book[idx][0] == price:
            if size == 0:
                book.pop(idx)
            else:
                book[idx][1] = size
        elif size > 0:
            # Insert new price level
            if side == 'buy':
                # For bids, we want DESC, so we search with -price
                insort(book, [price, size], key=lambda x: -x[0])
            else:
                insort(book, [price, size], key=lambda x: x[0])

    def get_best_bid(self) -> Optional[Tuple[float, float]]:
        return tuple(self.bids[0]) if self.bids else None

    def get_best_ask(self) -> Optional[Tuple[float, float]]:
        return tuple(self.asks[0]) if self.asks else None

    def get_liquidity_at_price(self, side: str, target_price: float) -> float:
        """Returns cumulative size available at or better than the target price."""
        book = self.bids if side == 'buy' else self.asks
        total_size = 0.0
        
        for price, size in book:
            if side == 'buy' and price >= target_price:
                total_size += size
            elif side == 'sell' and price <= target_price:
                total_size += size
            else:
                break
        return total_size

class PolymarketWSClient:
    """
    WebSocket client for Polymarket CLOB L2 data.
    """
    def __init__(self, tickers: List[str], uri: str = "wss://clob.polymarket.com/ws/l2"):
        self.tickers = tickers
        self.uri = uri
        self.cache: Dict[str, Orderbook] = {t: Orderbook(t) for t in tickers}
        self.is_running = False

    async def connect(self):
        """Main connection and message loop."""
        import websockets
        
        async for websocket in websockets.connect(self.uri):
            try:
                self.is_running = True
                # Subscribe to tickers
                subscribe_msg = {
                    "type": "subscribe",
                    "assets": self.tickers,
                    "channels": ["l2_updates"]
                }
                await websocket.send(json.dumps(subscribe_msg))
                logger.info(f"Subscribed to {self.tickers} on {self.uri}")

                async for message in websocket:
                    await self._handle_message(message)
            except websockets.ConnectionClosed:
                logger.warning("WebSocket connection closed. Reconnecting...")
                continue
            except Exception as e:
                logger.error(f"Unexpected error in WS loop: {e}")
                break

    async def _handle_message(self, message: str):
        data = json.loads(message)
        
        # Example Polymarket L2 format
        if data.get("type") == "l2_updates":
            ticker = data.get("asset_id")
            if ticker not in self.cache:
                return
                
            book = self.cache[ticker]
            for change in data.get("changes", []):
                side = 'buy' if change[0] == 'BUY' else 'sell'
                price = float(change[1])
                size = float(change[2])
                book.update(side, price, size)

    def check_preflight_slippage(self, ticker: str, side: str, price: float, min_size: float) -> bool:
        """
        O(1) (technically O(depth)) pre-flight check against local cache.
        """
        if ticker not in self.cache:
            return False
            
        available_size = self.cache[ticker].get_liquidity_at_price(side, price)
        return available_size >= min_size
