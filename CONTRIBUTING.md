# Contributing to Simple BuddyPress Signup Avatar

First off, thank you for considering contributing to Simple BuddyPress Signup Avatar! It's people like you that make this plugin a great tool for the WordPress community.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Where do I go from here?](#where-do-i-go-from-here)
- [Fork & Create a Branch](#fork--create-a-branch)
- [Get the Test Suite Running](#get-the-test-suite-running)
- [Implement Your Fix or Feature](#implement-your-fix-or-feature)
- [Get the Style Right](#get-the-style-right)
- [Make a Pull Request](#make-a-pull-request)
- [Keeping Your Pull Request Updated](#keeping-your-pull-request-updated)
- [Merging a PR (Maintainers Only)](#merging-a-pr-maintainers-only)
- [Shipping a Release (Maintainers Only)](#shipping-a-release-maintainers-only)

## Code of Conduct

This project and everyone participating in it is governed by the [Simple BuddyPress Signup Avatar Code of Conduct](CODE_OF_CONDUCT.md). By participating, you are expected to uphold this code. Please report unacceptable behavior to the [Plugin Author](https://github.com/flowerz88).

## Where do I go from here?

If you've noticed a bug or have a feature request, make sure to check our [Issues](https://github.com/flowerz88/simple-bp-signup-avatar/issues) page to see if someone else has already created a ticket. If not, go ahead and [make one](https://github.com/flowerz88/simple-bp-signup-avatar/issues/new)!

## Fork & Create a Branch

If this is something you think you can fix or implement yourself, here's how to get started:

1. Fork the repository to your own Github account
2. Clone the project to your machine
   ```bash
   git clone https://github.com/your-username/simple-buddypress-signup-avatar.git
   ```
3. Create a branch locally with a succinct but descriptive name
   ```bash
   git checkout -b 325-add-japanese-translations
   ```

## Get the Test Suite Running
Make sure you're using the latest version of WordPress and BuddyPress for testing.

To run the tests, follow these steps:

1. Install PHPUnit
2. Run `phpunit` in the plugin directory

## Implement Your Fix or Feature
At this point, you're ready to make your changes! Feel free to ask for help; everyone is a beginner at first.

## Get the Style Right
Your patch should follow the same coding conventions as the rest of the project. We use WordPress Coding Standards. You can check your code using PHP_CodeSniffer with the WordPress ruleset.

## Make a Pull Request
At this point, you should switch back to your master branch and make sure it's up to date with the master branch:

```bash
git remote add upstream git@github.com:original-owner-username/original-repository.git
git checkout master
git pull upstream master
```

Then update your feature branch from your local copy of master, and push it!

```bash
git checkout 325-add-japanese-translations
git rebase master
git push --set-upstream origin 325-add-japanese-translations
```

Finally, go to GitHub and [make a Pull Request](https://github.com/flowerz88/simple-bp-signup-avatar/compare)


## Keeping Your Pull Request Updated
If a maintainer asks you to "rebase" your PR, they're saying that a lot of code has changed, and that you need to update your branch so it's easier to merge.

Here's the suggested workflow:

```bash
git checkout 325-add-japanese-translations
git pull --rebase upstream master
git push --force-with-lease 325-add-japanese-translations
```

## Merging a PR (Maintainers Only)
A PR can only be merged into master by a maintainer if:

- It is passing CI.
- It has been approved by at least two maintainers. If it was a maintainer who opened the PR, only one extra approval is needed.
- It has no requested changes.
- It is up to date with current master.

Any maintainer is allowed to merge a PR if all of these conditions are met.

## Shipping a Release (Maintainers Only)
Maintainers need to do the following to push out a release:

- Make sure all tests are passing on the latest master.
- Update the version number in the plugin's main PHP file and readme.txt.
- Update the changelog in readme.txt.
- Commit these changes with the message "Bump to version X.X.X".
- Tag the release in git with the version number, e.g., git tag v1.0.0.
- Push the changes and the tag: git push && git push --tags.

## How to Report a Bug
When filing an issue, make sure to answer these five questions:

1. What version of WordPress and BuddyPress are you using?
2. What version of the plugin are you using?
3. What did you do?
4. What did you expect to see?
5. What did you see instead?

## How to Suggest a Feature or Enhancement
If you find yourself wishing for a feature that doesn't exist in Simple BuddyPress Signup Avatar, you are probably not alone. Open an issue on our issues list on GitHub which describes the feature you would like to see, why you need it, and how it should work.

Thank you for your contribution!
