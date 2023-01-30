#!/bin/bash

BASEDIR="./";
FILENAME="allofomeka.nt"

echo > merged0.nt
for FILE in ${BASEDIR}nt/*.nt ;
do
	cat ${FILE} >> ./merged0.nt
done

sort ./merged0.nt > ./merged1.nt
uniq ./merged1.nt > $FILENAME
rm ./merged?.nt
rm $FILENAME.gz
gzip $FILENAME

echo "INFO: finished making ${BASEDIR}${FILENAME}.gz"