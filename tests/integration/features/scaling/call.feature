Feature: scaling/call
  Background:
    Given group "company" exists
    And user "employee1" exists
    And user "employee1" is member of group "company"
    And user "employee2" exists
    And user "employee2" is member of group "company"
    And user "employee3" exists
    And user "employee3" is member of group "company"
    And user "employee4" exists
    And user "employee4" is member of group "company"
    And user "employee5" exists
    And user "employee5" is member of group "company"
    And user "employee6" exists
    And user "employee6" is member of group "company"
    And user "employee7" exists
    And user "employee7" is member of group "company"
    And user "employee8" exists
    And user "employee8" is member of group "company"
    And user "employee9" exists
    And user "employee9" is member of group "company"
    And user "employee10" exists
    And user "employee10" is member of group "company"
    And user "employee11" exists
    And user "employee11" is member of group "company"
    And user "employee12" exists
    And user "employee12" is member of group "company"
    And user "employee13" exists
    And user "employee13" is member of group "company"
    And user "employee14" exists
    And user "employee14" is member of group "company"
    And user "employee15" exists
    And user "employee15" is member of group "company"
    And user "employee16" exists
    And user "employee16" is member of group "company"
    And user "employee17" exists
    And user "employee17" is member of group "company"
    And user "employee18" exists
    And user "employee18" is member of group "company"
    And user "employee19" exists
    And user "employee19" is member of group "company"
    And user "employee20" exists
    And user "employee20" is member of group "company"
    And user "employee21" exists
    And user "employee21" is member of group "company"
    And user "employee22" exists
    And user "employee22" is member of group "company"
    And user "employee23" exists
    And user "employee23" is member of group "company"
    And user "employee24" exists
    And user "employee24" is member of group "company"
    And user "employee25" exists
    And user "employee25" is member of group "company"
    And user "employee26" exists
    And user "employee26" is member of group "company"
    And user "employee27" exists
    And user "employee27" is member of group "company"
    And user "employee28" exists
    And user "employee28" is member of group "company"
    And user "employee29" exists
    And user "employee29" is member of group "company"
    And user "employee30" exists
    And user "employee30" is member of group "company"
    And user "employee31" exists
    And user "employee31" is member of group "company"
    And user "employee32" exists
    And user "employee32" is member of group "company"
    And user "employee33" exists
    And user "employee33" is member of group "company"
    And user "employee34" exists
    And user "employee34" is member of group "company"
    And user "employee35" exists
    And user "employee35" is member of group "company"
    And user "employee36" exists
    And user "employee36" is member of group "company"
    And user "employee37" exists
    And user "employee37" is member of group "company"
    And user "employee38" exists
    And user "employee38" is member of group "company"
    And user "employee39" exists
    And user "employee39" is member of group "company"
    And user "employee40" exists
    And user "employee40" is member of group "company"
    And user "employee41" exists
    And user "employee41" is member of group "company"
    And user "employee42" exists
    And user "employee42" is member of group "company"
    And user "employee43" exists
    And user "employee43" is member of group "company"
    And user "employee44" exists
    And user "employee44" is member of group "company"
    And user "employee45" exists
    And user "employee45" is member of group "company"
    And user "employee46" exists
    And user "employee46" is member of group "company"
    And user "employee47" exists
    And user "employee47" is member of group "company"
    And user "employee48" exists
    And user "employee48" is member of group "company"
    And user "employee49" exists
    And user "employee49" is member of group "company"
    And user "employee50" exists
    And user "employee50" is member of group "company"
    And user "employee51" exists
    And user "employee51" is member of group "company"
    And user "employee52" exists
    And user "employee52" is member of group "company"
    And user "employee53" exists
    And user "employee53" is member of group "company"
    And user "employee54" exists
    And user "employee54" is member of group "company"
    And user "employee55" exists
    And user "employee55" is member of group "company"
    And user "employee56" exists
    And user "employee56" is member of group "company"
    And user "employee57" exists
    And user "employee57" is member of group "company"
    And user "employee58" exists
    And user "employee58" is member of group "company"
    And user "employee59" exists
    And user "employee59" is member of group "company"
    And user "employee60" exists
    And user "employee60" is member of group "company"
    And user "employee61" exists
    And user "employee61" is member of group "company"
    And user "employee62" exists
    And user "employee62" is member of group "company"
    And user "employee63" exists
    And user "employee63" is member of group "company"
    And user "employee64" exists
    And user "employee64" is member of group "company"
    And user "employee65" exists
    And user "employee65" is member of group "company"
    And user "employee66" exists
    And user "employee66" is member of group "company"
    And user "employee67" exists
    And user "employee67" is member of group "company"
    And user "employee68" exists
    And user "employee68" is member of group "company"
    And user "employee69" exists
    And user "employee69" is member of group "company"
    And user "employee70" exists
    And user "employee70" is member of group "company"

  Scenario: Company call
    Given enable query.log
    Then user "employee1" is participant of the following rooms (v4)
    Then user "employee2" is participant of the following rooms (v4)
    Then user "employee3" is participant of the following rooms (v4)
    Then user "employee4" is participant of the following rooms (v4)
    Then user "employee5" is participant of the following rooms (v4)
    Then user "employee6" is participant of the following rooms (v4)
    Then user "employee7" is participant of the following rooms (v4)
    Then user "employee8" is participant of the following rooms (v4)
    Then user "employee9" is participant of the following rooms (v4)
    Then user "employee10" is participant of the following rooms (v4)
    Then user "employee11" is participant of the following rooms (v4)
    Then user "employee12" is participant of the following rooms (v4)
    Then user "employee13" is participant of the following rooms (v4)
    Then user "employee14" is participant of the following rooms (v4)
    Then user "employee15" is participant of the following rooms (v4)
    Then user "employee16" is participant of the following rooms (v4)
    Then user "employee17" is participant of the following rooms (v4)
    Then user "employee18" is participant of the following rooms (v4)
    Then user "employee19" is participant of the following rooms (v4)
    Then user "employee20" is participant of the following rooms (v4)
    Then user "employee21" is participant of the following rooms (v4)
    Then user "employee22" is participant of the following rooms (v4)
    Then user "employee23" is participant of the following rooms (v4)
    Then user "employee24" is participant of the following rooms (v4)
    Then user "employee25" is participant of the following rooms (v4)
    Then user "employee26" is participant of the following rooms (v4)
    Then user "employee27" is participant of the following rooms (v4)
    Then user "employee28" is participant of the following rooms (v4)
    Then user "employee29" is participant of the following rooms (v4)
    Then user "employee30" is participant of the following rooms (v4)
    Then user "employee31" is participant of the following rooms (v4)
    Then user "employee32" is participant of the following rooms (v4)
    Then user "employee33" is participant of the following rooms (v4)
    Then user "employee34" is participant of the following rooms (v4)
    Then user "employee35" is participant of the following rooms (v4)
    Then user "employee36" is participant of the following rooms (v4)
    Then user "employee37" is participant of the following rooms (v4)
    Then user "employee38" is participant of the following rooms (v4)
    Then user "employee39" is participant of the following rooms (v4)
    Then user "employee40" is participant of the following rooms (v4)
    Then user "employee41" is participant of the following rooms (v4)
    Then user "employee42" is participant of the following rooms (v4)
    Then user "employee43" is participant of the following rooms (v4)
    Then user "employee44" is participant of the following rooms (v4)
    Then user "employee45" is participant of the following rooms (v4)
    Then user "employee46" is participant of the following rooms (v4)
    Then user "employee47" is participant of the following rooms (v4)
    Then user "employee48" is participant of the following rooms (v4)
    Then user "employee49" is participant of the following rooms (v4)
    Then user "employee50" is participant of the following rooms (v4)
    Then user "employee51" is participant of the following rooms (v4)
    Then user "employee52" is participant of the following rooms (v4)
    Then user "employee53" is participant of the following rooms (v4)
    Then user "employee54" is participant of the following rooms (v4)
    Then user "employee55" is participant of the following rooms (v4)
    Then user "employee56" is participant of the following rooms (v4)
    Then user "employee57" is participant of the following rooms (v4)
    Then user "employee58" is participant of the following rooms (v4)
    Then user "employee59" is participant of the following rooms (v4)
    Then user "employee60" is participant of the following rooms (v4)
    Then user "employee61" is participant of the following rooms (v4)
    Then user "employee62" is participant of the following rooms (v4)
    Then user "employee63" is participant of the following rooms (v4)
    Then user "employee64" is participant of the following rooms (v4)
    Then user "employee65" is participant of the following rooms (v4)
    Then user "employee66" is participant of the following rooms (v4)
    Then user "employee67" is participant of the following rooms (v4)
    Then user "employee68" is participant of the following rooms (v4)
    Then user "employee69" is participant of the following rooms (v4)
    Then user "employee70" is participant of the following rooms (v4)
    And note query.log: After employee boot
    When user "employee1" creates room "room" (v4)
      | roomType | 2 |
      | invite   | company |
    And note query.log: After creation
    When user "employee1" sets default permissions for room "room" to "CJ" with 200 (v4)
    And note query.log: After set permissions
    Then user "employee1" joins room "room" with 200 (v4)
    Then user "employee1" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee2" joins room "room" with 200 (v4)
    Then user "employee2" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee3" joins room "room" with 200 (v4)
    Then user "employee3" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee4" joins room "room" with 200 (v4)
    Then user "employee4" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee5" joins room "room" with 200 (v4)
    Then user "employee5" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee6" joins room "room" with 200 (v4)
    Then user "employee6" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee7" joins room "room" with 200 (v4)
    Then user "employee7" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee8" joins room "room" with 200 (v4)
    Then user "employee8" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee9" joins room "room" with 200 (v4)
    Then user "employee9" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee10" joins room "room" with 200 (v4)
    Then user "employee10" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee11" joins room "room" with 200 (v4)
    Then user "employee11" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee12" joins room "room" with 200 (v4)
    Then user "employee12" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee13" joins room "room" with 200 (v4)
    Then user "employee13" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee14" joins room "room" with 200 (v4)
    Then user "employee14" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee15" joins room "room" with 200 (v4)
    Then user "employee15" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee16" joins room "room" with 200 (v4)
    Then user "employee16" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee17" joins room "room" with 200 (v4)
    Then user "employee17" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee18" joins room "room" with 200 (v4)
    Then user "employee18" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee19" joins room "room" with 200 (v4)
    Then user "employee19" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee20" joins room "room" with 200 (v4)
    Then user "employee20" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee21" joins room "room" with 200 (v4)
    Then user "employee21" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee22" joins room "room" with 200 (v4)
    Then user "employee22" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee23" joins room "room" with 200 (v4)
    Then user "employee23" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee24" joins room "room" with 200 (v4)
    Then user "employee24" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee25" joins room "room" with 200 (v4)
    Then user "employee25" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee26" joins room "room" with 200 (v4)
    Then user "employee26" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee27" joins room "room" with 200 (v4)
    Then user "employee27" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee28" joins room "room" with 200 (v4)
    Then user "employee28" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee29" joins room "room" with 200 (v4)
    Then user "employee29" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee30" joins room "room" with 200 (v4)
    Then user "employee30" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee31" joins room "room" with 200 (v4)
    Then user "employee31" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee32" joins room "room" with 200 (v4)
    Then user "employee32" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee33" joins room "room" with 200 (v4)
    Then user "employee33" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee34" joins room "room" with 200 (v4)
    Then user "employee34" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee35" joins room "room" with 200 (v4)
    Then user "employee35" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee36" joins room "room" with 200 (v4)
    Then user "employee36" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee37" joins room "room" with 200 (v4)
    Then user "employee37" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee38" joins room "room" with 200 (v4)
    Then user "employee38" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee39" joins room "room" with 200 (v4)
    Then user "employee39" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee40" joins room "room" with 200 (v4)
    Then user "employee40" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee41" joins room "room" with 200 (v4)
    Then user "employee41" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee42" joins room "room" with 200 (v4)
    Then user "employee42" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee43" joins room "room" with 200 (v4)
    Then user "employee43" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee44" joins room "room" with 200 (v4)
    Then user "employee44" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee45" joins room "room" with 200 (v4)
    Then user "employee45" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee46" joins room "room" with 200 (v4)
    Then user "employee46" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee47" joins room "room" with 200 (v4)
    Then user "employee47" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee48" joins room "room" with 200 (v4)
    Then user "employee48" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee49" joins room "room" with 200 (v4)
    Then user "employee49" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee50" joins room "room" with 200 (v4)
    Then user "employee50" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee51" joins room "room" with 200 (v4)
    Then user "employee51" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee52" joins room "room" with 200 (v4)
    Then user "employee52" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee53" joins room "room" with 200 (v4)
    Then user "employee53" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee54" joins room "room" with 200 (v4)
    Then user "employee54" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee55" joins room "room" with 200 (v4)
    Then user "employee55" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee56" joins room "room" with 200 (v4)
    Then user "employee56" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee57" joins room "room" with 200 (v4)
    Then user "employee57" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee58" joins room "room" with 200 (v4)
    Then user "employee58" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee59" joins room "room" with 200 (v4)
    Then user "employee59" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee60" joins room "room" with 200 (v4)
    Then user "employee60" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee61" joins room "room" with 200 (v4)
    Then user "employee61" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee62" joins room "room" with 200 (v4)
    Then user "employee62" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee63" joins room "room" with 200 (v4)
    Then user "employee63" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee64" joins room "room" with 200 (v4)
    Then user "employee64" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee65" joins room "room" with 200 (v4)
    Then user "employee65" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee66" joins room "room" with 200 (v4)
    Then user "employee66" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee67" joins room "room" with 200 (v4)
    Then user "employee67" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee68" joins room "room" with 200 (v4)
    Then user "employee68" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee69" joins room "room" with 200 (v4)
    Then user "employee69" joins call "room" with 200 (v4)
      | flags | 1 |
    Then user "employee70" joins room "room" with 200 (v4)
    Then user "employee70" joins call "room" with 200 (v4)
      | flags | 1 |
    And note query.log: After all joins
    When user "employee1" ends call "room" with 200 (v4)
    And note query.log: After end call
    And disable query.log
