@local @local_ltiidentity
Feature: Teachers complete the profile of LTI users of their course
  In order to see real names instead of LTI placeholders
  As a teacher
  I need to edit names of the LTI users enrolled in my course, and only those

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                | auth   |
      | teacher1 | Tessa     | Teacher  | teacher1@example.com | manual |
      | teacher2 | Tom       | Other    | teacher2@example.com | manual |
      | ltilearn | ltisub1   | https://platform.example.org | ltilearn@example.com | lti |
      | ltiother | ltisub2   | https://platform.example.org | ltiother@example.com | lti |
      | manual1  | Mandy     | Manual   | manual1@example.com  | manual |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
      | Course 2 | C2        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C2     | editingteacher |
      | ltilearn | C1     | student        |
      | manual1  | C1     | student        |
      | ltiother | C2     | student        |

  Scenario: The list shows only LTI users of the course
    Given I am on the "Course 1" course page logged in as "teacher1"
    When I navigate to "LTI users" in current page administration
    Then I should see "ltisub1"
    And I should not see "Mandy"
    And I should not see "ltisub2"

  Scenario: A teacher completes the name of an LTI user and the field is locked
    Given I am on the "Course 1" course page logged in as "teacher1"
    And I navigate to "LTI users" in current page administration
    When I follow "Complete profile"
    And I set the following fields to these values:
      | First name | Ada       |
      | Last name  | Lovelace  |
    And I press "Save changes"
    Then I should see "The profile was saved."
    And I should see "Ada Lovelace"
    And I should see "entered by a teacher"

  Scenario: Placeholder values are refused as names
    Given I am on the "Course 1" course page logged in as "teacher1"
    And I navigate to "LTI users" in current page administration
    When I follow "Complete profile"
    And I set the following fields to these values:
      | First name | Ada |
      | Last name  |     |
    And I press "Save changes"
    Then I should see "Please enter a value."

  Scenario: A student does not get the teacher page
    Given I log in as "manual1"
    And the account of "manual1" uses LTI authentication
    And I am on the "Course 1" course page
    Then I should not see "LTI users"
    And I should not see "LTI users" in the "region-main" "region"
