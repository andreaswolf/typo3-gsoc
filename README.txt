This is a demo extension for the export of TYPO3 data structures (and later on data) to RDF. Put this tree in a folder rdf_export in your typo3conf/ext/ dir.

It needs a special TYPO3 Core to run; see https://github.com/andreaswolf/typo3-tceforms/tree/tceforms-widgets for that


To enable access via typo3/data/<table>/<uid> URLs, add this line to your .htaccess:

RewriteRule typo3/data/([a-zA-Z-_]*)/([1-9][0-9]*) index.php?eID=rdf_export_endpoint&controllerName=Export&actionName=exportRecord&arguments[datastructure]=$1&arguments[uid]=$2&arguments[exportformat]=turtle [L]

Note that you have to add it above the RewriteRule that stops processing for the typo3/ directory.
