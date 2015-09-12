telluswhere - installation notes
================================

A full install script is at:
https://github.com/cyclestreets/cyclestreets-setup/tree/master/install-telluswhere/

However, the key requirements are as follows:

```
# Obtain the repo
git clone git clone https://github.com/cyclestreets/telluswhere.git

# Requires PDO SQLite
apt-get install php5-sqlite

# Useful for command-line interaction with database; see: http://sqlite.org/sqlite.html
apt-get install sqlite3

# Ensure the database directory and tmp directory are writable
chown -R www-data db/ tmp/
```
