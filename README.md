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

As root, run the command `mkdir ~apache/.ssh && chmod 700 ~apache/.ssh && chown apache.apache ~apache/.ssh && su -s /bin/sh -c "ssh-keygen" apache`

If you already have a gitolite set up, add apaches fresh public key to gitolite and grant it access to the gitolite-admin repository.

If this is a fresh install, run this command to set up gitolite and give apache access:
`cat ~apache/.ssh/id_rsa.pub > /tmp/ssh.pub && su -s /bin/sh -c "gitolite setup -pk /tmp/ssh.pub" gitolite3 && rm /tmp/ssh.pub`

Make sure apaches .ssh folder and the files within it are only readable to apache.
You can do this by running the command `chown -R apache.apache ~apache/.ssh && chmod g=,o= ~apache/.ssh{,/*}`

Create the folder where you want to keep the admin repository, I opted for /var/www/data:
`mdkir /var/www/data && chown apache.apache /var/www/data`

Before you can clone the repository, apache needs to trust the host, so run the command:
`su -s /bin/sh apache` to open a shell as apache, then `ssh git-server` and accept the host key. Then hit ctrl+d to return to your root shell and continue.

Clone the gitolite-admin repository as apache;
`su -s /bin/sh -c "cd /var/www/data && git clone gitolite@git-server:gitolite-admin" apache`

Edit index.php and make sure `$admin` points to the correct path.

Now navigate to the admin site, and create groups as you see fit.

Important information
=====================
Before you click the push button, make sure you have granted access to gitolite-admin for the key you added for apache, or you will lock yourself out of the system.

Usage
=====
This tool enforces a group-based control of access. Repositories and users are assigned to groups and then you assign permissions to user groups to a group of repositories.

When you make a change, always hit Save.

When you have made all your changes and saved, you can hit Push to activate the changes on the server.
