describe('Full Journal Transfer - Graphical display', function () {
    it('Checks message shown by plugin in its page', function () {
		cy.login('dbarnes', null, 'publicknowledge');

		cy.contains('a.app__navItem', 'Tools').click();

		cy.contains('a', 'Journal Export Plugin').click();

		cy.contains('h1', 'Journal Export Plugin');
        cy.contains('Attention');
        cy.contains('This plugin should be used only by command line. The use via the graphical interface is not supported.');
        cy.contains('For more information, check: ');
        cy.contains('a', 'Instructions for use');
    });
});