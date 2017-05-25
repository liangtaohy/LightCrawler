#!/usr/bin/env bash
appname="LightCrawler"
PRO_DIR="phpsrc/app/"${appname}
PRO_FILE=${appname}".tar.gz"

rm -rf output/*

mkdir -p output/${PRO_DIR}

cp -rf  README.md	build.sh	data		deploy.sh	includes	interface	libs		vendor		worker output/${PRO_DIR}/

cd output

find ./ -type d -name .git|xargs -i rm -rf {}
find ./ -type d -name .svn|xargs -i rm -rf {}
find ./ -type d -name .gitignore|xargs -i rm -rf {}

tar cvzf $PRO_FILE *

scp $PRO_FILE work@60.205.169.24:/home/work/liangtao/
ssh work@60.205.169.24 "cd /home/work/liangtao && tar -zxvf "${PRO_FILE}" -C /home/work/xdp/"
