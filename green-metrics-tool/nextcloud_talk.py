import contextlib
import os
import random
import string
import sys
import signal
from time import sleep, time_ns

from playwright.sync_api import Playwright, sync_playwright, expect, TimeoutError

from helpers.helper_functions import log_note, get_random_text, login_nextcloud, close_modal, timeout_handler, user_sleep


DOMAIN = os.environ.get('HOST_URL', 'http://app')

TYPING_DELAY_MS = 200
TALK_INVITEE_COUNT = 5

def send_message(sender, message):
    log_note("Sending message")
    sender.get_by_role("textbox").click()
    sender.keyboard.type(message, delay=TYPING_DELAY_MS)
    sender.get_by_role("textbox").press("Enter")
    #log_note("GMT_SCI_R=1")

def create_conversation(playwright: Playwright, browser_name: str) -> str:
    log_note(f"Launch browser {browser_name}")
    if browser_name == "firefox":
        browser = playwright.firefox.launch(headless=False)
    else:
        browser = playwright.chromium.launch(headless=False, args=['--disable-gpu', '--disable-software-rasterizer', '--ozone-platform=wayland'])
    context = browser.new_context(ignore_https_errors=True)
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

        log_note("Open Talk app")
        page.locator('#header a[title=Talk]').click()
        page.wait_for_url("**/apps/spreed/")
        user_sleep()

        log_note("Create conversation")
        #page.click("span.chat-plus-icon")
        page.get_by_text("Create a new conversation").click()
        # Different placeholder names and capitalization on apache vs FPM
        page.get_by_placeholder("name").fill("Random talk")
        page.get_by_text("Allow guests to join via link").click()
        page.get_by_role("button", name="Create conversation").click()
        user_sleep()

        log_note('Copy conversation link')
        page.get_by_role("button", name="Copy link").click()
        user_sleep()

        log_note("Close browser")

        # ---------------------
        page.close()
        context.close()
        browser.close()

        return page.url

    except Exception as e:
        if hasattr(e, 'message'): # only Playwright error class has this member
            log_note(f"Exception occurred: {e.message}")

        # set a timeout. Since the call to page.content() is blocking we need to defer it to the OS
        signal.signal(signal.SIGALRM, timeout_handler)
        signal.alarm(20)
        log_note(f"Page content was: {page.content()}")
        signal.alarm(0) # remove timeout signal
        raise e

def talk(playwright: Playwright, url: str, browser_name: str) -> None:

    # Launch browsers
    log_note(f"Launching {TALK_INVITEE_COUNT} {browser_name} browsers")
    if browser_name == "firefox":
        browsers = [playwright.firefox.launch(headless=False) for _ in range(TALK_INVITEE_COUNT)]
    else:
        browsers = [playwright.chromium.launch(headless=False, args=['--disable-gpu', '--disable-software-rasterizer', '--ozone-platform=wayland']) for _ in range(TALK_INVITEE_COUNT)]
    contexts = [browser.new_context(ignore_https_errors=True) for browser in browsers]
    pages = [context.new_page() for context in contexts]

    # Go to URL for all users
    log_note("Navigating to Talk conversation with all participants")
    for page in pages:
        page.goto(url)
    user_sleep()

    # Perform actions for all users
    log_note("Setting guest usernames for all participants")
    for page in pages:
        page.get_by_placeholder("Guest").fill(f"Person #{pages.index(page) + 1}")
        page.get_by_role("button", name="Submit name and join").click()
    user_sleep()

    # Send first message and check for visibility
    log_note("Send the first validation message")
    sender = pages[0]
    message = "Let's send some random text!"
    send_message(sender, message)
    user_sleep()

    log_note("Validate the first message got received")
    for page in pages[1:]:
        expect(page.get_by_text(message, exact=True)).to_be_visible()
    user_sleep()

    # Send random text and validate it was received by other users
    log_note("Start sending random messages")
    for i, sender in enumerate(pages):
        receivers = pages[:i] + pages[i + 1:]
        random_text = get_random_text(50)

        send_message(sender, random_text)
        user_sleep()

        log_note('Validating if all users received the message')
        for receiver in receivers:
            expect(receiver.get_by_text(random_text, exact=True)).to_be_visible()
        log_note("Message received by all users")
        user_sleep()

    # --------------------
    # Close all users
    log_note("Close all browsers")
    for page in pages:
        page.close()

    for context in contexts:
        context.close()

    for browser in browsers:
        browser.close()


with sync_playwright() as playwright:
    if len(sys.argv) > 1:
        browser_name = sys.argv[1].lower()
        if browser_name not in ["chromium", "firefox"]:
            print("Invalid browser name. Please choose either 'chromium' or 'firefox'.")
            sys.exit(1)
    else:
        browser_name = "firefox"

    conversation_link = create_conversation(playwright, browser_name)
    talk(playwright, conversation_link, browser_name)
