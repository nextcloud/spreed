import contextlib
import os
import sys
from time import time_ns, sleep
import signal
import random
import string
import re
from multiprocessing import Pool

from playwright.sync_api import Playwright, sync_playwright, expect

from helpers.helper_functions import log_note, login_nextcloud, close_modal, timeout_handler, user_sleep

DOMAIN = os.environ.get('HOST_URL', 'http://app')

CHAT_SESSIONS = 1
CHAT_TIME_SEC = 60

def join(browser_name: str, download_url:str) -> None:
    with sync_playwright() as playwright:
        log_note(f"Launching join browser {browser_name}")
        if browser_name == "firefox":
            browser = playwright.firefox.launch(headless=False,
                                                firefox_user_prefs = {
                                                    "media.navigator.streams.fake": True,
                                                    "media.navigator.permission.disabled": True
                                                },
                                                args=['-width', '1280', '-height', '720']
                                            )
        else:
            browser = playwright.chromium.launch(headless=False, args=['--disable-gpu', '--disable-software-rasterizer', '--ozone-platform=wayland', '--window-size=1280,720'])

        context = browser.new_context(
            ignore_https_errors=True,
            viewport={'width': 1280, 'height': 720}
        )
        page = context.new_page()

        try:
            log_note('Opening call link with participant')
            page.goto(download_url)
            user_sleep()

            log_note('Setting name and requesting to join')
            guest_name = "Guest " + ''.join(random.choices(string.ascii_letters, k=5))
            page.get_by_placeholder('Guest').fill(guest_name)
            #page.get_by_role('button', name="Submit name and join").click()
            #page.get_by_role("button", name="Join call").click()
            user_sleep()
            page.locator('.media-settings').get_by_role("button", name="Join call").click()
            log_note(f"{guest_name} joined the chat")
            user_sleep()

            log_note(f"Staying the chat and calling for {CHAT_TIME_SEC}s")
            sleep(CHAT_TIME_SEC)

            log_note('Leaving call with participant')
            page.get_by_role("button", name="Leave call").click()
            user_sleep()

            page.close()
            log_note("Close participant browser")

        except Exception as e:
            if hasattr(e, 'message'): # only Playwright error class has this member
                log_note(f"Exception occurred: {e.message}")

            # set a timeout. Since the call to page.content() is blocking we need to defer it to the OS
            signal.signal(signal.SIGALRM, timeout_handler)
            signal.alarm(20)
            #log_note(f"Page content was: {page.content()}")
            signal.alarm(0) # remove timeout signal

            raise e

        # ---------------------
        context.close()
        browser.close()

def run(playwright: Playwright, browser_name: str) -> None:
    log_note(f"Launch browser {browser_name}")
    if browser_name == "firefox":
        browser = playwright.firefox.launch(headless=False,
                                            firefox_user_prefs = {
                                                "media.navigator.streams.fake": True,
                                                "media.navigator.permission.disabled": True
                                            },
                                            args=['-width', '1280', '-height', '720']
                                        )
    else:
        browser = playwright.chromium.launch(headless=False, args=['--disable-gpu', '--disable-software-rasterizer', '--ozone-platform=wayland', '--window-size=1280,720'])

    context = browser.new_context(
        ignore_https_errors=True,
        viewport={'width': 1280, 'height': 720}
    )
    page = context.new_page()

    try:
        log_note("Opening login page")
        page.goto(f"{DOMAIN}/login")

        log_note("Logging in")
        login_nextcloud(page, domain=DOMAIN)
        user_sleep()

        # Wait for the modal to load. As it seems you can't close it while it is showing the opening animation.
        log_note("Close first-time run popup")
        close_modal(page)

        log_note("Go to Talk app")
        page.locator('#header a[title=Talk]').click()
        page.wait_for_url("**/apps/spreed/")
        user_sleep()

        log_note("Start new chat session")
        #page.locator('button.action-item__menutoggle:has(.chat-plus-icon)').click()
        page.get_by_text("Create a new conversation").click()
        chat_name = "Chat " + ''.join(random.choices(string.ascii_letters, k=5))
        page.get_by_placeholder('Enter a name for this conversation').fill(chat_name)
        page.locator(f'text="Allow guests to join via link"').click()
        page.get_by_role("button", name="Create conversation").click()
        user_sleep()

        log_note('Copying conversation link')
        page.get_by_role("button", name="Copy link").click()

        page.get_by_role("dialog", name=re.compile(r'^All set, the conversation', re.I)) \
            .get_by_label("Close") \
            .click()

        #page.locator('.modal-container').get_by_role('button', name="Close").click()
        link_url = page.url# evaluate('navigator.clipboard.readText()')
        log_note(f"Chat url is: {link_url}")
        user_sleep()

        log_note('Starting the call')
        page.get_by_role("button", name="Start call").click()
        page.locator('.media-settings').get_by_role("button", name="Start call").click()
        user_sleep()

        log_note(f"Starting {CHAT_SESSIONS} Chat clients")
        args = [(browser_name, link_url) for _ in range(CHAT_SESSIONS)]
        with Pool(processes=CHAT_SESSIONS) as pool:
            pool.starmap(join, args)

        log_note('Leaving the call with the host')
        page.get_by_role("button", name="Leave call").click()
        #page.get_by_role('menuitem', name='Leave call').click()
        user_sleep()

        page.close()
        log_note("Close browser")

    except Exception as e:
        if hasattr(e, 'message'): # only Playwright error class has this member
            log_note(f"Exception occurred: {e.message}")

        # set a timeout. Since the call to page.content() is blocking we need to defer it to the OS
        signal.signal(signal.SIGALRM, timeout_handler)
        signal.alarm(20)
        #log_note(f"Page content was: {page.content()}")
        signal.alarm(0) # remove timeout signal

        raise e

    # ---------------------
    context.close()
    browser.close()


if __name__ == "__main__":
    if len(sys.argv) > 1:
        browser_name = sys.argv[1].lower()
        if browser_name not in ["chromium", "firefox"]:
            print("Invalid browser name. Please choose either 'chromium' or 'firefox'.")
            sys.exit(1)
    else:
        browser_name = "firefox"

    with sync_playwright() as playwright:
        run(playwright, browser_name)
