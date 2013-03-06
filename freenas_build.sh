#!/bin/sh

#MASTER_SITE_OVERRIDE=http://localhost:8000/
#export MASTER_SITE_OVERRIDE

# do the needful for using our local git repo
USE_GIT=yes
export USE_GIT
#export GIT_BRANCH="freenas-dev-9.1a"
export GIT_BRANCH="freenas"
# Set me to the git tag when making a subversion tag.
#export GIT_TAG=

# do not apply patches for FreeBSD source tree,
# instead simply use what is in git.
export SKIP_SOURCE_PATCHES="yes"

# important that this is single quoted so that the shell
# does NOT expand ${DIST_SUBDIR}, this allows make(1) to
# expand it at the correct time.
#export MASTER_SITE_OVERRIDE='http://localhost/ports-distfiles/${DIST_SUBDIR}/'

sh build/do_build.sh
