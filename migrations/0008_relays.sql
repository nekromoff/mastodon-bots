CREATE TABLE IF NOT EXISTS relays (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    inbox_url          TEXT    NOT NULL,
    account_id         INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
    state              TEXT    NOT NULL DEFAULT 'pending' CHECK(state IN ('pending','accepted','rejected')),
    follow_activity_id TEXT    NOT NULL DEFAULT '',
    created_at         TEXT    NOT NULL DEFAULT (datetime('now')),
    UNIQUE(inbox_url, account_id)
);
