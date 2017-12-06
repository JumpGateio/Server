Welcome to Server Commands
=============================
This package contains helpful commands created for managing the Nuka Code server.

Install
-------
Run the following composer command to install Jumpgate Commands globally.

    composer global require jumpgate/commands

Make sure to place the ``~/.composer/vendor/bin`` directory in your PATH so the laravel executable can be located by your system.

    echo 'export PATH="$HOME/.composer/vendor/bin:$PATH"' >> ~/.bash_profile
    source ~/.bash_profile

New Site Command
-----
The new command will create an Nginx config in ``~/nginx/sites-avaiable`` and then sym link it to the sites enable directory.
After this is done it will create a directory for the domain in ``~/sites``. Once this is complete it will restart Nginx using ``sudo service nginx reload``.

For best results make sure to allow the command ``service nginx reload`` via your sudo configuration.

    commands new-site sub.domain.com
