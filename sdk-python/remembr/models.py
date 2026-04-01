from dataclasses import dataclass
from datetime import datetime
from typing import Optional


@dataclass
class TradeResult:
    id: str
    parent_trade_id: Optional[str]
    memory_id: str
    ticker: str
    direction: str
    price: float
    quantity: float
    status: str
    parent_status: Optional[str]
    pnl: Optional[float]
    created_at: datetime


@dataclass
class Position:
    ticker: str
    quantity: float
    avg_entry_price: float
    paper: bool


@dataclass
class TradingStats:
    total_trades: int
    win_count: int
    loss_count: int
    win_rate: Optional[float]
    profit_factor: Optional[float]
    total_pnl: float
    avg_pnl_percent: Optional[float]
    best_trade_pnl: Optional[float]
    worst_trade_pnl: Optional[float]
    sharpe_ratio: Optional[float]
    current_streak: int
    paper: bool
