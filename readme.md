# Sphinx search integration for Magento

## Installation (Debian GNU/Linux)

- Download the deb from http://sphinxsearch.com/downloads/release/ and install

$ sudo aptitude install libpq5 && dpkg -i sphinxsearch_2.0.6-release-1_amd64.deb

- Copy sys/* to /etc/sphinxsearch/ and change credentials in sphinx.conf to match your setup.

- Run "$ ./modman repair" in magento root if you use modman (https://github.com/colinmollenhour/modman) or copy files into your magento root

- Clear your Magento Cache, logout and login again to trigger installation / db table creation

- Rebuild catalog search index. This should fill the new table 'catalogsearch_fulltext_sphinx'.

- Run "$ sudo indexer --all && sudo service sphinxsearch start" to build initial index and start sphinx

- Create cronjob to rotate index, e.g. "0 3 * * * root indexer --all --rotate && service sphinxsearch restart"

- Try "$ search foo" in shell to test your indexed shopdata. Try searching your shop and enjoy ultrafast and relevant search results.

## Things to try

- Tweak stopwords and wordforms

## Credits

- The initial idea is from: http://tonyhb.co.uk/2012/05/using-sphinx-within-magento-plus-optimising-search-result-rankings-weights-and-relevancy/

- Stopword lists are from: http://snowball.tartarus.org/

## License

This program is free software; you can redistribute it and/or modify it under the terms of the
GNU General Public License as published by the Free Software Foundation; either version 2 of the
License, or (at your option) any later version. See COPYING file for details.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the
Free Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA