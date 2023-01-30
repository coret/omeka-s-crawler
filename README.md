# omeka-s-crawler
Crawl all JSON-LD via the Omeka S API to make a compressed N-triple file for import.

# Install

Run `php composer.phar update` to get the [EasyRDF module](https://www.easyrdf.org/) (which is installed in the `vendor` directory).

More info about PHP Composer: https://getcomposer.org/

# Run

`crawler.php` crawls all Omeka S items (and related resources) in JSON-LD format. The JSON-LD is converted to N-triples and stored in the `nt` directory. The `hashes` directory is home for the MD5 hashes of the N-triple files and are used to skip the unnecessary conversion if there are no changes in contents.

If the ARK module is used and `dcterms:identifier` triples are found, then an additional `owl:sameAs` triple is added to 'connect' a https://n2t.net/ark:/99999/xxxxxx like URI to the Omeka S API URI.

The Bash-script `convert.sh` collects all N-triple files from the `nt` directory, sorts them (making lines uniq) and Gzip's the resulting file.

# Caveats

Crawling all items can take hours, conversion only minutes. 

A Omeka S module which makes the N-triple file on each item creation/update and deletes the N-triple file on item delete would be more efficient. 

For shorter (re)crawls the `$next=$omeka_base.'/api/items?per_page=100&sort_by=id&sort_order=asc&page=1';` can be adjusted to narrow down the crawl to a specific item site.