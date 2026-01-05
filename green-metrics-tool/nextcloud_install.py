import os
import sys
import signal
from time import time_ns
from dotenv import load_dotenv
from playwright.sync_api import sync_playwright

from helpers.helper_functions import log_note, timeout_handler, user_sleep

load_dotenv()

DOMAIN = os.environ.get('HOST_URL', 'http://app')

def main(browser_name: str = "firefox", headless=False):
    with sync_playwright() as playwright:
        log_note(f"Launch browser {browser_name}")
        signal.signal(signal.SIGALRM, timeout_handler)
        signal.alarm(10)
        if browser_name == "firefox":
            browser = playwright.firefox.launch(headless=headless)
        else:
            browser = playwright.chromium.launch(headless=False, args=['--disable-gpu', '--disable-software-rasterizer', '--ozone-platform=wayland'])
        context = browser.new_context(ignore_https_errors=True)
        page = context.new_page()
        signal.alarm(0) # remove timeout signal
        print(f"Opening {DOMAIN}")
        try:
            page.goto(DOMAIN)

            # 1. Create User
            log_note("Create admin user")
            page.locator('input[name="adminlogin"]').fill('nextcloud')
            page.locator('input[name="adminpass"]').fill('nextcloud')

#            page.get_by_text("Storage & database").click()
            page.get_by_text("MySQL/MariaDB").click()

            page.wait_for_selector('input[name="dbuser"]')
            page.locator('input[name="dbuser"]').fill('nextcloud')
            page.locator('input[name="dbname"]').fill('nextcloud')
            page.locator('input[name="dbpass"]').fill('nextcloud')
            page.locator('input[name="dbhost"]').fill('db')
            page.get_by_role("button", name="Install").click()

            # 2. Install all Apps
            # log_note("Install recommended apps")
            # install_selector = '.button-vue--vue-primary'
            # page.locator(install_selector).click()
            page.get_by_text("Skip").click()

            # 3. Dashboard
            page.locator('.app-dashboard').wait_for(state='visible', timeout=240_000)
            log_note("Installation complete")
            browser.close()

        except Exception as e:
            if hasattr(e, 'message'): # only Playwright error class has this member
                log_note(f"Exception occurred: {e.message}")

            # set a timeout. Since the call to page.content() is blocking we need to defer it to the OS
            signal.signal(signal.SIGALRM, timeout_handler)
            signal.alarm(20)
            log_note(f"Page content was: {page.content()}")
            signal.alarm(0) # remove timeout signal
            raise e


if __name__ == '__main__':
    if len(sys.argv) > 1:
        browser_name = sys.argv[1].lower()
        if browser_name not in ["chromium", "firefox"]:
            print("Invalid browser name. Please choose either 'chromium' or 'firefox'.")
            sys.exit(1)
    else:
        browser_name = "firefox"

    main(browser_name)
