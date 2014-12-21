#!/bin/bash

SCRIPT_DIR=$(cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd)
DATA_DIR=$(cd "$( dirname "${BASH_SOURCE[0]}" )" && cd ../../../data/kickass && pwd)
NB_PROCESSES=5

#cleaning data directory
$(rm -rf $DATA_DIR/*)

#Downloading and decompressing data
$(wget --no-check-certificate https://kickass.so/dailydump.txt.gz && gzip --uncompres *.gz && mv *.txt $DATA_DIR && rm -rf *.gz)

#Splitting file
NB_LINES=$(cat $DATA_DIR/*.txt | wc -l)
NB_LINES_PER_PROCESS=$((NB_LINES/NB_PROCESSES))
$(cd $DATA_DIR && mkdir tmp && cd tmp && split -l $NB_LINES_PER_PROCESS ../*.txt)

#Spawing processes
for f in $DATA_DIR/tmp/*; do
    php -f $SCRIPT_DIR/new-data.php $f &
done