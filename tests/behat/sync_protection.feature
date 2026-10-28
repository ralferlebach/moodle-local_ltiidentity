@local @local_ltiidentity
Feature: Locally entered names survive the real auth_lti profile sync
  In order to keep the names teachers entered for LTI users
  As a teacher
  I need repeated, incomplete, contradicting and changing LTI claims never to overwrite a locked value

  # The sync step runs core's auth_plugin_lti::find_or_create_user_from_membership(), which calls the same
  # update_user_account() as a launch. Nothing is mocked: the protection comes from the before_user_updated hook.
  Background:
    Given the following "users" exist:
      | username | firstname | lastname                     | email                | auth   |
      | teacher1 | Tessa     | Teacher                      | teacher1@example.com | manual |
      | ltilearn | ltisub1   | https://platform.example.org | ltilearn@example.com | lti    |
      | peer1    | Pete      | Peer                         | peer1@example.com    | manual |
    And the account of "ltilearn" is bound to the LTI platform user "ltisub1"
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | ltilearn | C1     | student        |
      | peer1    | C1     | student        |

  Scenario: A teacher-entered name survives repeated syncs with missing, contradicting and changing claims
    Given I am on the "Course 1" course page logged in as "teacher1"
    And I navigate to "LTI users" in current page administration
    And I follow "Complete profile"
    And I set the following fields to these values:
      | First name | Ada      |
      | Last name  | Lovelace |
    And I press "Save changes"
    When the LTI platform syncs "ltisub1" without claims
    And the LTI platform syncs "ltisub1" without claims
    And the LTI platform syncs "ltisub1" with the claims:
      | given_name  | Augusta |
      | family_name | King    |
    And the LTI platform syncs "ltisub1" with the claims:
      | given_name | Countess |
    And I navigate to "LTI users" in current page administration
    Then I should see "Ada Lovelace"
    And I should not see "Augusta"
    And I should not see "Countess"
    And I am on the "Course 1" "enrolled users" page
    And I should see "Ada Lovelace"

  Scenario: Without a lock real claims replace placeholders and a later sync without claims keeps them
    When the LTI platform syncs "ltisub1" with the claims:
      | given_name  | Claire |
      | family_name | Claim  |
    And the LTI platform syncs "ltisub1" without claims
    And I am on the "Course 1" course page logged in as "teacher1"
    And I navigate to "LTI users" in current page administration
    Then I should see "Claire Claim"
    And I should not see "Name not provided by the platform"

  Scenario: An administrator releases a lock, then the platform claim wins again for that field only
    Given I am on the "Course 1" course page logged in as "teacher1"
    And I navigate to "LTI users" in current page administration
    And I follow "Complete profile"
    And I set the following fields to these values:
      | First name | Ada      |
      | Last name  | Lovelace |
    And I press "Save changes"
    And I log out
    And I am on the "Course 1" course page logged in as "admin"
    And I navigate to "LTI users" in current page administration
    And I click on "Release lock" "link" in the "Ada Lovelace" "table_row"
    And I press "Confirm"
    And I should see "The lock was released."
    When the LTI platform syncs "ltisub1" with the claims:
      | given_name  | Augusta |
      | family_name | King    |
    And I navigate to "LTI users" in current page administration
    Then I should see "Augusta Lovelace"
