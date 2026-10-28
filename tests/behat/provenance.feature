@local @local_ltiidentity
Feature: The profile page shows where the name and e-mail of an LTI user came from
  In order to know whether a name was transmitted by the platform or entered locally
  As a teacher or as the LTI user
  I need the origin of first name, last name and e-mail on the profile page, and nobody else may see it

  Background:
    Given the following "users" exist:
      | username | firstname | lastname                     | email                | auth   |
      | teacher1 | Tessa     | Teacher                      | teacher1@example.com | manual |
      | ltilearn | ltisub1   | https://platform.example.org | ltilearn@example.com | manual |
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

  Scenario: Teacher sees the origin on the course profile, the learner on the own profile, a peer sees nothing
    Given the account of "ltilearn" uses LTI authentication
    And the LTI platform syncs "ltisub1" with the claims:
      | given_name | Claire |
    And I log in as "teacher1"
    And I am on the "Course 1" course page
    And I navigate to "LTI users" in current page administration
    And I follow "Complete profile"
    And I set the following fields to these values:
      | First name | Clara  |
      | Last name  | Claims |
    And I press "Save changes"
    When I open the course profile of "ltilearn" in "C1"
    Then I should see "Origin of LTI profile data"
    And I should see "A teacher - locked against LTI launches"
    And I should see "Not recorded: unchanged since the account was created"
    And I log out
    And I log in as "peer1"
    And I open the course profile of "ltilearn" in "C1"
    And I should not see "Origin of LTI profile data"

  Scenario: The learner sees the origin of a transmitted name on the own profile and on the self-service page
    Given I log in as "ltilearn"
    And the account of "ltilearn" uses LTI authentication
    And the LTI platform syncs "ltisub1" with the claims:
      | given_name  | Claire |
      | family_name | Claim  |
    When I am on the "ltilearn" "user > profile" page
    Then I should see "Origin of LTI profile data"
    And I should see "LTI platform (transmitted at a launch)"
    And I visit "/local/ltiidentity/profile.php"
    And I should see "LTI platform (transmitted at a launch)" in the "table.local-ltiidentity-provenance" "css_element"
