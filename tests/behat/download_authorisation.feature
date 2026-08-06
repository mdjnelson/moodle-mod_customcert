@mod @mod_customcert
Feature: Certificate downloads verify issuance before generating a PDF
  In order to prevent certificate data being generated for the wrong user
  As a developer
  I need personal-download requests to verify an issue exists first

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity   | name                 | intro                      | course | idnumber    |
      | customcert | Custom certificate 1 | Custom certificate 1 intro | C1     | customcert1 |

  Scenario: An anonymous user cannot probe whether another user has been issued a certificate
    When I attempt to download the personal certificate PDF for "student1" in "Custom certificate 1"
    Then I should not see "You have not been issued a certificate"
    And I should see "Username"
    And I should see "Password"
