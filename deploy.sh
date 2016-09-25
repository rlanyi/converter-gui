#!/bin/bash

rsync -avcz --delete --exclude-from=".rsyncignore" . robyn@10.1.10.252:~/admin/
ssh robyn@10.1.10.252 "sudo rm -rf /home/robyn/admin/cache/*"