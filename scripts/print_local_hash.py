import sqlite3
conn = sqlite3.connect('data/bantayaso.sqlite')
row = conn.execute("SELECT password_hash FROM users WHERE username='admin'").fetchone()
print(row[0])
