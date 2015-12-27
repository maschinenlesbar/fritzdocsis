FritzDOCSIS
============
This PHP script glues together three components to provide Trending, Monitoring and Graphing of
Fritz!Box DOCSIS Information obtainable only from the WebUI. This Plugin was developed for
the Fritz!Box 6360 with Unitymedia branding but is known to work with other AVM cable devices too.

This script uses a library from Gregor Nathanael Meyer <Gregor [at] der-meyer.de>.
Also used is a library for working with World of Warcraft LUA files from  david.stangeby@gmail.com.

Requirements
------------
This code requires PHP5 / PHP7 and the PHP `curl` extension.

Configuration
-------------
Set variables in .php file and chmod 700. Then run `php fritzdocsis_.php suggest`. This will output
suggestions for all in-built plugins.


German HowTo
------------
There is a blog article explaining this script in German:

http://falkhusemann.de/blog/2012/09/fritzbox-cable-mit-munin-uberwachen-ohne-alternative-firmware/
