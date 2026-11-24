<?php

// Patches are retired (Core 0.38: customized modules replace them). This stays, doing nothing, because the
// updater from Core 0.37 and older runs it after checking out a new Core, and older docker-compose.yml files
// mount it (a missing file would become a folder).
exit(0);
