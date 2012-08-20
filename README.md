About
=====
This is a minimalistic PHP driven web interface for gitolite-admin.

Status
======
This tool should now be good to go, but the codebase needs some cleanup. Fortunately, there isn't much of it.

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
