# Browser-test targets for this module, sourced by the shared runner
# (~/Sites/0_ss-mods-maintenance/tools/browser/run.sh). Plain bash assignments only.

BROWSER_PACKAGE="restruct/silverstripe-quickaddnew"
BROWSER_TARGETS="ss5 ss6"

# Silverstripe 5 is served by the v2 line (2.x); this branch (3.x) requires framework ^6.
# SS5_SRC_REF is checked out as a detached worktree next to the host; override with SS5_SRC=/path.
SS5_RECIPE="^5"
SS5_PHP="8.3"
SS5_PORT="8855"
SS5_SRC_REF="origin/v2"

# Silverstripe 6 is served by this working tree (empty ref = the checkout the runner was given).
SS6_RECIPE="^6"
SS6_PHP="8.3"
SS6_PORT="8856"
SS6_SRC_REF=""
