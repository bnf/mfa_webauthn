COMPOSER_EXECUTABLE=composer

build:
	cd build && npm ci && npm run rollup

prepare-classic-extension:
	rm -rf Resources/Private/Libraries/*/
	cd Resources/Private/Libraries && $(COMPOSER_EXECUTABLE) install --no-dev --no-autoloader --ansi
	rm -rf Resources/Private/Libraries/composer/

build-t3x-extension: prepare-classic-extension
	rm -f "$${PWD##*/}_`git describe --tags`.zip"
	git archive -o "$${PWD##*/}_`git describe --tags`.zip" HEAD
	zip -r -g "$${PWD##*/}_`git describe --tags`.zip" Resources/Private/Libraries/
	@echo
	@echo "$${PWD##*/}_`git describe --tags`.zip has been created."
