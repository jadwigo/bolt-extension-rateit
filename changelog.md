# Changelog for RateIt

* 2.0.4

 * Ensure number format keeps a dot for decimals (credit @bobdenotter & jadwigo)
 * Catch divide by zero if first vote is reset (credit @jadwigo)

* 2.0.3 (2015-04-01)

 * Allow custom messages in the config file
 * Prevent vote stuffing on unlocked rating

* 2.0.2 (2015-03-31)

 * Allow vote to be reset (credit @jadwigo)
 * Make locking the rating stars on a record after a vote is cast optional with `lock_after_vote: true`

* 2.0.1 (2015-02-05)

 * Only load one (minified) JavaScript file
 * Change to a proper controller implementation

* 2.0.0 (2014-12-17)

 * Initial release for Bolt 2.0.0
