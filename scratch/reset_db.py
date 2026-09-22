import sqlite3

def reset():
    conn = sqlite3.connect('wlc.db')
    cursor = conn.cursor()
    cursor.execute("UPDATE settings SET value = 'WLC Wahidin' WHERE key = 'app_name'")
    cursor.execute("UPDATE settings SET value = '' WHERE key = 'app_logo'")
    conn.commit()
    print("Database settings successfully reset!")
    cursor.execute("SELECT * FROM settings")
    print(cursor.fetchall())
    conn.close()

if __name__ == '__main__':
    reset()
