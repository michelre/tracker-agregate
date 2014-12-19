#!/bin/bash

SCRIPT_PATH=$(cd "$(dirname "${BASH_SOURCE[0]}" )" && pwd)

start_crawler(){
    until $(php -f $SCRIPT_PATH/$1/new-data.php); do
        echo "Server 'myserver' crashed with exit code $?.  Respawning.." >&2
        sleep 10
    done
}

while getopts ":t:" opt; do
    case $opt in
        t)
            start_crawler $OPTARG
            ;;
        \?)
            echo "Please specify a tracker with -t"
            ;;
        :)
            echo "Please specify a tracker name"
            ;;
    esac
done