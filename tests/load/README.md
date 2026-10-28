# Load tests - out of scope

local_ltiidentity has no high-volume or capacity-limited path: the only hot spot is the
`before_user_updated` hook callback, which runs a handful of indexed primary-key / `userid` lookups
per LTI launch. Its cost and idempotence (five consecutive syncs, no extra rows) are covered by
`tests/hook_callbacks_test.php`. A load plan would add no evidence; none is shipped, and no
JMeter/k6 workflow exists for this plugin.
