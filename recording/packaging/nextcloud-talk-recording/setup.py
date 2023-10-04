# Dummy setup.py file to be used by stdeb; setuptools >= 61.0 must be used to
# get the proper configuration from the pyproject.toml file.

from setuptools import setup

setup(
    # pyproject.toml uses different keywords that are not properly converted to
    # the old ones, so they need to be explicitly set here to be used by stdeb.
    # "author" can not be set without "author_email". Moreover, if the email was
    # also set in pyproject.toml it could not be set here either, as due to how
    # the parameters are internally handled by stdeb it would end mixing the
    # author set here with the author and email set in pyproject.toml.
    url = "https://github.com/nextcloud/spreed",
)
