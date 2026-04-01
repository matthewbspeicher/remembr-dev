import unittest
import sys
import os

# Add python/journal to path
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))

from python.journal.orderbook import Orderbook

class TestOrderbook(unittest.TestCase):
    def setUp(self):
        self.book = Orderbook("TEST-MARKET")

    def test_bid_sorting(self):
        self.book.update("buy", 100.0, 10.0)
        self.book.update("buy", 105.0, 5.0)
        self.book.update("buy", 95.0, 20.0)
        
        # Bids should be sorted DESC
        prices = [level[0] for level in self.book.bids]
        self.assertEqual(prices, [105.0, 100.0, 95.0])
        
        # Best bid should be 105.0
        self.assertEqual(self.book.get_best_bid(), (105.0, 5.0))

    def test_ask_sorting(self):
        self.book.update("sell", 110.0, 10.0)
        self.book.update("sell", 108.0, 5.0)
        self.book.update("sell", 115.0, 20.0)
        
        # Asks should be sorted ASC
        prices = [level[0] for level in self.book.asks]
        self.assertEqual(prices, [108.0, 110.0, 115.0])
        
        # Best ask should be 108.0
        self.assertEqual(self.book.get_best_ask(), (108.0, 5.0))

    def test_liquidity_at_price(self):
        self.book.update("buy", 100.0, 10.0)
        self.book.update("buy", 99.0, 10.0)
        self.book.update("buy", 98.0, 10.0)
        
        # Cumulative size at or better than 99.0 should be 20.0 (100.0 + 99.0)
        self.assertEqual(self.book.get_liquidity_at_price("buy", 99.0), 20.0)
        
        # Cumulative size at or better than 101.0 should be 0.0
        self.assertEqual(self.book.get_liquidity_at_price("buy", 101.0), 0.0)

    def test_removal(self):
        self.book.update("buy", 100.0, 10.0)
        self.assertEqual(len(self.book.bids), 1)
        
        # Update with size 0 should remove
        self.book.update("buy", 100.0, 0.0)
        self.assertEqual(len(self.book.bids), 0)

if __name__ == '__main__':
    unittest.main()
