@local @local_ltiidentity
Feature: LTI users complete their own profile
  In order to be addressed by name
  As an LTI user
  I need a page for my own name and e-mail address

  Background:
    Given the following "users" exist:
      | username | firstname | lastname                     | email                | auth   |
      | ltilearn | ltisub1   | https://platform.example.org | ltilearn@example.com | manual |
      | manual1  | Mandy     | Manual                       | manual1@example.com  | manual |

  Scenario: An LTI user saves a name
    Given I log in as "ltilearn"
    And the account of "ltilearn" uses LTI authentication
    And I visit "/local/ltiidentity/profile.php"
    When I set the following fields to these values:
      | First name | Grace  |
      | Last name  | Hopper |
    And I press "Save changes"
    Then I should see "The profile was saved."
    And the field "First name" matches value "Grace"

  Scenario: An e-mail address is only requested, not applied
    Given I log in as "ltilearn"
    And the account of "ltilearn" uses LTI authentication
    And I visit "/local/ltiidentity/profile.php"
    When I set the following fields to these values:
      | First name | Grace                   |
      | Last name  | Hopper                  |
      | New e-mail address (optional) | grace@example.org |
    And I press "Save changes"
    Then I should see "A confirmation link was sent to the new e-mail address."

  Scenario: Only LTI users get the profile entry in their preferences
    Given I log in as "ltilearn"
    And the account of "ltilearn" uses LTI authentication
    When I visit "/user/preferences.php"
    Then I should see "My LTI profile"
    When I log out
    And I log in as "manual1"
    And I visit "/user/preferences.php"
    Then I should not see "My LTI profile"
    # The refusal itself (page ends in a moodle_exception, which Behat cannot assert) is covered by
    # profile_service_test::test_self_service_access and navigation_test::test_user_settings_entry_for_lti_user_only.
