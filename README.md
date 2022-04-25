# Bug Report

**TLDR; `fileHasPHP` produces a much higher rate of false-positives since PHP `8.0`.**

## Introduction and context

We run a multi-site WordPress system and use Wordfence to add another layer
of protection. Wordfence especialy helps us protect our system where WordPress
is directly exposed to the public internet.

Recently we updated our system from PHP `7.3` to PHP `8.0`, updated WordPress
and updated Wordfence to the latest available releases. This was a relatively painless
process.

After a couple of days one of our clients noticed some of their content editors
were unable to upload new files. The files they were trying to upload were regular
JPEG files, we tried some other random JPEGs from the internet, most of them were blocked.

At first we thought the issue might be caused by the custom WordPress roles we had assigned to
the users that were experiencing the issue. These users don't have the standard 
`Administrator`, `Editor` or `Author` roles. Instead they only have custom roles.
This, though, did not explain why the issue only appeared after the update.

After combing through all the diffs and disabling all plugins one by one we figured out that
The issue only appeared in PHP `8.0` and when Wordfence was enabled.

So at this point we had the following information:

|                                                | PHP 7 | PHP 8   |
| ---------------------------------------------- | ----- | ------- |
| **Role**                                       |       |         |
| `Administrator`                                | Ok    | Ok      |
| `Editor`                                       | Ok    | Ok      |
| `Author`                                       | Ok    | Ok      |
| `Form Editor (WP Administrator)` (custom role) | Ok    | Blocked |
| **Plugin**                                     |       |         |
| Wordfence enabled                              | Ok    | Blocked |
| Wordfence disabled                             | Ok    | Ok      |
| other plugins disabled                         | Ok    | Blocked |

Next we started adding log lines to Wordfence to figure out which Wordfence code
was blocking the files. We found that the `Malicious File Upload (PHP)` rule would trip
in PHP 8 for users with a custom role. When looking at the rule definition we could easily
explain why it would only block for custom roles; the rule is bypassed for the standard 
`Administrator`, `Editor` or `Author` roles. This leaves `fileHasPHP` as the only condition
in the rule which might cause the problem.

## `fileHasPHP` or does it?

Next we isolated the [`fileHasPHP`][fileHasPHP] function, and simplified it a bit so that we
could test it without having to load all of WordPress and Wordfence (The modified code is
[here][simpleFileHasPHP]). We collected some test files. And tested the function in all PHP
versions since `5.3`.

As we expected, this function works differently in PHP `8.x` versus PHP `7.x`/`5.x`. **Since PHP `8.0`
it is very likely to block binary files like JPEGs.**

### Test Results

| File                    | PHP `5.3` | PHP `5.4` | PHP `5.5` | PHP `5.6` | PHP `7.0` | PHP `7.1` | PHP `7.2` | PHP `7.3` | PHP `7.4` | PHP `8.0` | PHP `8.1` |
| ----------------------- | --------- | --------- | --------- | --------- | --------- | --------- | --------- | --------- | --------- | --------- | --------- |
| `test/test.php`         | OK        | OK        | OK        | OK        | OK        | OK        | OK        | OK        | OK        | OK        | OK        |
| `test/test-file-01.txt` | OK        | OK        | OK        | OK        | OK        | OK        | OK        | OK        | OK        | OK        | OK        |
| `test/test-file-02.jpg` | OK        | OK        | OK        | OK        | OK        | OK        | OK        | OK        | OK        | **FAIL**  | **FAIL**  |
| `test/test-file-03.php` | OK        | OK        | OK        | OK        | OK        | OK        | OK        | OK        | OK        | OK        | OK        |
| `test/test-file-04.jpg` | OK        | OK        | OK        | OK        | OK        | OK        | OK        | OK        | OK        | **FAIL**  | **FAIL**  |
| `test/test-file-05.jpg` | OK        | OK        | OK        | OK        | OK        | OK        | OK        | OK        | OK        | OK        | OK        |
| `test/test-file-06.jpg` | OK        | OK        | OK        | OK        | OK        | OK        | OK        | OK        | OK        | **FAIL**  | **FAIL**  |


  [fileHasPHP]: wordfence-7.5.9/vendor/wordfence/wf-waf/src/lib/rules.php#L897
  [simpleFileHasPHP]: test/test.php#L22
