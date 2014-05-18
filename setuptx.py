#!/usr/bin/env python
import os
import fnmatch

def locate(root, pattern):
    '''Locate all files matching supplied filename pattern in and below
    supplied root directory.'''
    for path, dirs, files in os.walk(root):
        for filename in fnmatch.filter(files, pattern):
            if os.path.getsize(os.path.join(path, filename)) > 0:
                yield os.path.join(path, filename)

def gatherpotfiles():
  lst = locate (".", "*.pot")
  for item in lst:
    base = item[0:item.rfind("/")];
    print (base)
    chunks = item.split("/")
    if chunks[1]=="global":
      resource = chunks[1]
    else:
      resource = chunks[1]+"_"+chunks[2]
    print ("")
    print (resource)
    cmd = "tx set --auto-local -r 'chyrp."+resource+"' '"+base+"/<lang>.po' --source-lang en_US --source-file "+item+" --execute"
#    print (cmd)
    os.system (cmd)




gatherpotfiles()
