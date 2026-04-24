# Not Implemented Modules

This document lists the modules from the capstone matrix that are not fully implemented in the current `Code-Cat` project, based on the intended system design.

## Summary

### Not Implemented

No clearly required module is fully missing based on the clarified design that:

- students are the ones who play the game
- teachers create classrooms and levels, enroll students, and monitor progress
- admins manage reporting

### Partially Implemented

| Module | Role | Status | Notes |
|---|---|---|---|
| Logic Check | Student | Partially Implemented | The game UI shows `if (condition)`, but the condition logic is not fully executed in the compiler and currently returns `true` unconditionally. |

### Not Applicable by Design

| Module | Role | Status | Notes |
|---|---|---|---|
| Gameplay Testing | Teacher | Not Applicable | Teachers are intentionally limited to classroom management, level creation, student enrollment, and progress monitoring. |
| Gameplay Testing | Admin | Not Applicable | Admin users are intended for report generation and system oversight, not gameplay. |

## Basis

- Teacher accounts are redirected to `teacher_levels.php`, which matches the intended teacher workflow of classroom management and student monitoring.
- The teacher dashboard already supports classroom creation, student enrollment, level management, and progress tracking.
- Admin access is focused on user reporting through `reports.php` and `export.php`.
- Logic checking is incomplete in `code-cat/coding/compiler.gd` because `_condition()` exists but is not fully integrated into actual `if` statement execution.

## Recommended Wording for Capstone Paper

You can describe the current status like this:

> The system already implements the intended role-based workflow of the project: students use the gameplay modules, teachers manage classrooms and monitor student progress, and admins handle report generation. At present, the only partially implemented gameplay-related module is the logic checking feature in the in-game code compiler.
