#/bin/bash

echo "double-checking that installation has worked successfully";
chmod +x installer.sh
./insaller.sh

if [$1 == "Y"]; then
  chmod +x recondash.sh
  ./recondash.sh
elif [$1 == "N"]; then
 echo "You chose not to run the bash scripts";
else 
 echo "ERROR: Unknown error has taken place";
fi
