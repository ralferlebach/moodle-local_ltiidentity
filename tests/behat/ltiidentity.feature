@local @local_ltiidentity
Feature: LTI Identity is installed and configurable
  In order to protect locally entered names of LTI users
  As an administrator
  I need the plugin to be installed and its settings to be reachable

  Scenario: The plugin is installed
    Given I log in as "admin"
    And I navigate to "Plugins > Plugins overview" in site administration
    Then I should see "LTI Identity"

  Scenario: The administrator can read and save the settings
    Given I log in as "admin"
    And I navigate to "Plugins > Local plugins > LTI Identity" in site administration
    Then I should see "Priority of LTI claims"
    And I should see "Duplicate e-mail addresses"
    When I set the field "Priority of LTI claims" to "Existing local values always win"
    And I press "Save changes"
    Then the field "Priority of LTI claims" matches value "Existing local values always win"
