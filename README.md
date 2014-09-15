t3ext-dam_falmigration
======================

TYPO3 Extension: Migrate DAM Records and Relations to TYPO3 6.2s File Abstraction Layer (FAL)

This extension only works with TYPO3 >= 6.2 and MySQL, because there are some Queries using GROUP_CONCAT.

Introduction
============

First of all: Remove all scheduler tasks of this extension you have defined previously in scheduler module. That's
because of a completely rewritten code. Scheduler serializes the task-classes with all its properties and these
properties are now modified. So, If you don't do this step, the task will throw Exceptions because of undefined
property names.

All tasks are now executed from the command line.

Installation and Usage
======================

More information on [Installation](Documentation/Installation/Index.rst) and [Usage](Documentation/UserManual/Index.rst) can be found in the [documentation folder](Documentation/Index.rst).

**no files will be moved or copied**