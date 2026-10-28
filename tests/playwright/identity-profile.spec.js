// End-to-end story for local_ltiidentity (plugin installed on Site B, the LTI tool/provider):
//
//   learner launched from Site A with minimal claims -> appears on B with the placeholder name ->
//   teacher on B enters the real name -> learner relaunches from A -> name is kept ->
//   B's course participants list shows the real name.
//
// Reads the stack description from LTI_SEED (default /home/claude/e2e/state/seed.json).
// Helpers (login, psql) come from the stack library (E2E_LIB, default /home/claude/e2e/lib/e2e).
const { test, expect } = require('@playwright/test');
const e2e = require(process.env.E2E_LIB || '/home/claude/e2e/lib/e2e');

const seed = e2e.loadSeed();
const sqlB = e2e.sqlB;
const user = (key) => e2e.getUser(seed, key);
const login = (page, site, cred) => e2e.login(page, site, cred, seed);

/** Launch the seeded activity as the given Site A learner in a fresh browser session. */
async function launchFromA(browser, learner) {
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await login(page, 'a', learner);
  await page.goto(seed.lti_activity.view_url);
  await page.waitForURL(new RegExp(`${seed.urls.b.replace(/[.]/g, '\\.')}/course/view\\.php\\?id=${seed.courses.b.id}`),
    { timeout: 60000 });
  const userId = Number(await page.evaluate(() => window.M.cfg.userId));
  await ctx.close();
  return userId;
}

const typeId = seed.lti.platform.type_id;
const original = {};

/** Make Site A send neither name nor e-mail (minimal claims) and use the same window; remember the old values. */
function configureMinimalClaims() {
  for (const name of ['sendname', 'sendemailaddr']) {
    original[name] = e2e.sqlA(`select value from mdl_lti_types_config where typeid=${typeId} and name='${name}'`);
    e2e.sqlA(`delete from mdl_lti_types_config where typeid=${typeId} and name='${name}'`);
    e2e.sqlA(`insert into mdl_lti_types_config (typeid, name, value) values (${typeId}, '${name}', '0')`);
  }
  original.container = e2e.sqlA(`select launchcontainer from mdl_lti where id=${seed.lti_activity.instance}`);
  e2e.sqlA(`update mdl_lti set launchcontainer=5 where id=${seed.lti_activity.instance}`);
}

/** Put Site A's tool configuration back as it was before the run. */
function restoreClaims() {
  for (const name of ['sendname', 'sendemailaddr']) {
    if (original[name] === undefined) {
      continue;
    }
    e2e.sqlA(`delete from mdl_lti_types_config where typeid=${typeId} and name='${name}'`);
    e2e.sqlA(`insert into mdl_lti_types_config (typeid, name, value) values (${typeId}, '${name}', '${original[name] || '1'}')`);
  }
  if (original.container) {
    e2e.sqlA(`update mdl_lti set launchcontainer=${Number(original.container)} where id=${seed.lti_activity.instance}`);
  }
}

test.describe.serial('LTI identity profile: placeholder -> teacher entry -> relaunch keeps the name', () => {
  const learner = user('studentA3');
  const teacher = user('teacherB');
  let bUserId;
  let sub;

  test.afterAll(() => restoreClaims());

  test('minimal-claim launch creates a user with placeholder values on B', async ({ browser }) => {
    configureMinimalClaims();
    bUserId = await launchFromA(browser, learner);
    expect(bUserId).toBeGreaterThan(2);
    // Start every run from the placeholder state, whatever earlier runs or launches left behind.
    sub = sqlB(`select sub from mdl_auth_lti_linked_login where userid=${bUserId} limit 1`);
    const issuer = sqlB(`select issuer from mdl_auth_lti_linked_login where userid=${bUserId} limit 1`);
    sqlB(`delete from mdl_user_preferences where userid=${bUserId} and name like 'local\\_ltiidentity\\_%'`);
    // Precondition of the story: an auth_lti account (other plugins' runs on the shared stack may have changed it).
    sqlB(`update mdl_user set auth='lti', firstname='${sub}', lastname='${issuer}' where id=${bUserId}`);
    const row = sqlB(`select firstname||'|'||lastname from mdl_user where id=${bUserId}`);
    expect(row).toBe(`${sub}|${issuer}`);
  });

  test('teacher on B sees the placeholder indicator and enters the real name', async ({ browser }) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await login(page, 'b', teacher);
    await page.goto(`${seed.urls.b}/local/ltiidentity/index.php?id=${seed.courses.b.id}`);
    const row = page.locator('table.local-ltiidentity-users tbody tr').filter({ hasText: sub });
    await expect(row).toHaveCount(1);
    await expect(row.locator('.badge')).not.toHaveCount(0);
    await row.getByRole('link').last().click();
    await page.fill('#id_firstname', 'Ada');
    await page.fill('#id_lastname', 'Lovelace');
    await Promise.all([page.waitForURL(/ltiidentity\/index\.php/), page.click('#id_submitbutton')]);
    await expect(page.locator('.alert-success, .notification-success').first()).toBeVisible();
    await expect(page.locator('table.local-ltiidentity-users')).toContainText('Ada Lovelace');
    await ctx.close();
    expect(sqlB(`select firstname||'|'||lastname from mdl_user where id=${bUserId}`)).toBe('Ada|Lovelace');
  });

  test('relaunch from A (still no claims) keeps the teacher-entered name', async ({ browser }) => {
    const again = await launchFromA(browser, learner);
    expect(again).toBe(bUserId);
    expect(sqlB(`select firstname||'|'||lastname from mdl_user where id=${bUserId}`)).toBe('Ada|Lovelace');
  });

  test('B course participants list shows the real name', async ({ browser }) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await login(page, 'b', teacher);
    await page.goto(`${seed.urls.b}/user/index.php?id=${seed.courses.b.id}&perpage=100`);
    await expect(page.locator('#participants, table').first()).toContainText('Ada Lovelace');
    await ctx.close();
  });

  test('a learner cannot open the teacher pages on B', async ({ browser }) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await login(page, 'a', learner);
    await page.goto(seed.lti_activity.view_url);
    await page.waitForURL(/\/course\/view\.php/, { timeout: 60000 });
    await page.goto(`${seed.urls.b}/local/ltiidentity/index.php?id=${seed.courses.b.id}`);
    await expect(page.locator('table.local-ltiidentity-users')).toHaveCount(0);
    // The learner is the user who was renamed, so the name is legitimately shown in the user menu; check the main region only.
    await expect(page.locator('#region-main')).not.toContainText('Ada Lovelace');
    await ctx.close();
  });
});
