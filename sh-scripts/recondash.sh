#!/bin/bash

if [$1 = "quick"]; then
 // run less extensive scanning
elif [$1 = "normal"]; then
 // run normal scanning
elif [$1 = "full"]; then
 // run most extensive scanning options
else
 echo "ERROR: Unknown error!";
fi
