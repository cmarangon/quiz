MAIN_BRANCH ?= main
DEVELOP_BRANCH ?= develop

.PHONY: release

# Merge develop into main, push, then return to develop.
release:
	@if [ -n "$$(git status --porcelain)" ]; then \
		echo "Error: working directory is not clean. Commit or stash your changes first." >&2; \
		exit 1; \
	fi
	@echo "==> Checking out '$(MAIN_BRANCH)'"
	git checkout $(MAIN_BRANCH)
	@echo "==> Merging '$(DEVELOP_BRANCH)' into '$(MAIN_BRANCH)'"
	git merge --no-ff $(DEVELOP_BRANCH)
	@echo "==> Pushing '$(MAIN_BRANCH)' to origin"
	git push origin $(MAIN_BRANCH)
	@echo "==> Switching back to '$(DEVELOP_BRANCH)'"
	git checkout $(DEVELOP_BRANCH)
	@echo "==> Release complete!"
