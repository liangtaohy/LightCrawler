#!/bin/bash

PRO_DIR="phpsrc/app/task"
CONF_DIR="phpsrc/conf/task"
PRO_FILE="task.tar.gz"
TPL_DIR="templates/templates/task/site/template"

mkdir -p output

rm -rf output/*

mkdir -p output/$PRO_DIR
mkdir -p output/$CONF_DIR
mkdir -p output/$TPL_DIR

cp -rf actions library models controller testcases oncetask routine common index.php output/$PRO_DIR/
cp -rf conf/* output/$CONF_DIR/

cd output

find ./ -type d -name .git|xargs -i rm -rf {}
find ./ -type d -name .svn|xargs -i rm -rf {}

tar cvzf $PRO_FILE *

rm -rf phpsrc templates

cd ..