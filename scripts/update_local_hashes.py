import sqlite3

admin_hash = "$argon2id$v=19$m=65536,t=4,p=2$OXhHYVJ4MG5WUUJpM3RUeQ$EG4xv5YkONLm884828DYid++kKKslhMnVBKDSlynXpc"
viewer_hash = "$argon2id$v=19$m=65536,t=4,p=2$dkllS1Vja2ptUEUwT1Z2VA$OB5zrAprTmblP9BSBtaY/OU/WCIYz7QK/Ji4s269Q3c"

conn = sqlite3.connect('data/bantayaso.sqlite')
c = conn.cursor()
c.execute('UPDATE users SET password_hash = ? WHERE username = ?', (admin_hash, 'admin'))
c.execute('UPDATE users SET password_hash = ? WHERE username = ?', (viewer_hash, 'viewer'))
conn.commit()
rows = c.execute('SELECT id, username, email, role, password_hash FROM users').fetchall()
for r in rows:
    print(r[0], r[1], r[2], r[3], r[4])
