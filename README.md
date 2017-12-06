Welcome to Server Commands
=============================
This package contains helpful commands created for managing the Nuka Code server.

Install
-------
THIS DOES NOT WORK RIGHT NOW
Run the following composer command to install the JumpGate installer globally.

    composer global require "jumpgate/server-commands=~1.0"

Make sure to place the ``~/.composer/vendor/bin`` directory in your PATH so the laravel executable can be located by your system.

    echo 'export PATH="$HOME/.composer/vendor/bin:$PATH"' >> ~/.bash_profile
    source ~/.bash_profile

Usage
-----
Once installed, the simple server-command new command will create a fresh Laravel installation in the directory you specify.
For instance, laravel new blog would create a directory named blog containing a fresh Laravel installation with all dependencies installed.
This method of installation is much faster than installing via Composer:

    server-command new site your.domain.com
