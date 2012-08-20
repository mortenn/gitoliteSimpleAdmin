About
=====
This is a minimalistic PHP driven web interface for gitolite-admin.

Status
======
This is fairly complete, but it is lacking support for some permissions.
While the gitolite permissions are defined as the regex -|R|RW+?C?D?M? this currently support C|[RW+]+
Obviously, this means it is currently possible to specify invalid permission sets.
Until this can be improved upon, users of this tool need to be a bit careful.

If you want more advanced permissions than this tool currently supports, you may be better off editing the config manually.

Design principals
=================
- Keep it simple
- Delegate access controls to httpd
- Use jQuery UI to make it look snazzy
- Strictly enforce group-based configuration of gitolite

Installing
==========
**These instructions were written with CentOS 6 in mind.**

As root, run the command `su -s /bin/sh -c "ssh-keygen" apache`

Add apaches fresh public key to gitolite and grant it access to the gitolite-admin repository.

Make sure apaches .ssh folder and the files within it are only readable to apache.
You can do this by running the command `chown -R apache.apache ~apache/.ssh && chmod g=,o= ~apache/.ssh{,/*}`

Create the folder where you want to keep the admin repository, I opted for /var/www/data:
`mdkir /var/www/data && chown apache.apache /var/www/data`

Clone the gitolite-admin repository as apache;
`su -s /bin/sh -c "cd /var/www/data && git clone gitolite@git-server:gitolite-admin" apache`

Edit index.php and make sure `$admin` points to the correct path.
