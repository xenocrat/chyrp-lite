#!/usr/bin/python
from path import *
import re

def sanitize(filename):
    """
    Walks the directory recursively and sanitizes filenames that match the given pattern.
    """
    for f in path(".").walkfiles(filename):
        source = f.text()

        if re.search("[\t ]+\n", source):
            print "/".join((f.parent, f.name)) + " has whitespace before a newline"

        if re.search("[ ]+\t", source):
            print "/".join((f.parent, f.name)) + " has tabs after spaces"

        sanitized = re.sub("[\t ]+\n", "\n", source)
        sanitized = re.sub("([ ]+)\t", "\\1    ", sanitized)
        f.write_text(sanitized)

sanitize("*.php")
sanitize("*.rb")
sanitize("*.js")
sanitize("*.css")
sanitize("*.twig")
sanitize("triggers_list")