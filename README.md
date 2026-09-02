## Service 
### 1. The Service Class = Reusability

Yes, the primary goal of the Service class is reusability. Because a controller must have only one responsibility (receiving the request and returning the response), you move the heavy business logic into a service class.

If your boss asks you to build a totally different screen—like an "Admin Master Dashboard"—that also needs to calculate weekly percentages, you do not have to copy and paste code. You simply call `$this->weeklyPlanService->calculatePreviousWeekSummary()` from the new admin controller.

### 2. Eloquent = Readable & Maintainable Code

Yes, if you did not use Eloquent, you would be forced to write raw SQL strings directly into your PHP files. Eloquent allows you to write readable and maintainable code by replacing raw database commands with object-oriented PHP.

By using Eloquent and isolating your logic, your code becomes self-documenting. A new developer could read your file and instantly understand what it does without needing a database manual.


## Request 
Automatic Rejection: If a React frontend sends target_graduated: -5, Laravel automatically throws a 422 Unprocessable Entity error. Your controller code doesn't even run.

Cleaner Controllers: By doing this, we avoid writing $request->validate([...]) inside the controller itself, strictly following the rule to move validation out of controllers.


## Data flow 
How the Flow Completes
The user types "3" into the Monday input.

They click "Save Day".

React Hook Form grabs all the numbers in that specific row.

Axios sends the PATCH request to Laravel.

Our DailyMetricController receives it, the UpdateDailyMetricRequest validates that it is a safe integer, and the database updates Monday's record.


## PATCH METHOD
Use **`PATCH`** because it means **Partial Update**.

* **`POST`** creates a new row (Monday already exists, so you can't use this).
* **`PUT`** replaces the entire row (If you don't send every single column, the missing ones might be overwritten and wiped out).
* **`PATCH`** only updates the fields you send. If you only send `"train_completed": 2`, it changes that one number and leaves the rest of Monday's data perfectly safe.
