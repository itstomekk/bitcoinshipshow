"""No page embeds a player; the replay is linked, never framed.

The homepage hero carries the countdown only, and the last expedition's replay
is a tile in the gallery (the episode archive). /live/ used to head with the
channel's live_stream player; that section was taken off on 2026-10-06 because
outside a broadcast the player renders an empty box, so /live/ is now the
countdown + lineup page and both pages link out instead of embedding.
"""
import unittest
from pathlib import Path

SITE = Path(__file__).parents[1]
PAGE = SITE / "index.php"
LIVE = SITE / "live" / "index.php"


class StreamEmbedTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.source = PAGE.read_text(encoding="utf-8")
        cls.live = LIVE.read_text(encoding="utf-8")

    def test_front_page_does_not_embed_a_video(self):
        self.assertNotIn("<iframe", self.source)
        self.assertNotIn("data-stream-player", self.source)
        self.assertNotIn("data-stream-link", self.source)

    def test_front_page_links_the_e01_replay_from_the_archive(self):
        self.assertIn("https://www.youtube.com/watch?v=wkG0b-86teM", self.source)
        self.assertIn("img/episodes/e01.jpg", self.source)
        self.assertIn('class="shot shot-ep shot-ep-wide"', self.source)

    def test_live_page_redirects_to_the_homepage(self):
        """/live/ was retired on 2026-10-07 (Tomek: "should redirect to main page
        anyway"): it is a permanent redirect now, so it must not grow a page back
        (a lineup or a countdown here would be a second source of truth)."""
        self.assertNotIn("<iframe", self.live)
        self.assertNotIn("data-countdown", self.live)
        self.assertIn("https://bitcoinship.show/", self.live)
        self.assertIn("301", self.live)


if __name__ == "__main__":
    unittest.main()
