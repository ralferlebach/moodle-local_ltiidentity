// IP-02 / IP-04 negative cases with real two-site LTI 1.3 launches (Site A = Moodle 4.5 platform, Site B = Moodle 5.3
// tool with local_ltiidentity):
//
//   incomplete claims (name without e-mail) -> changing claims -> teacher lock vs. contradicting claims ->
//   claims withdrawn -> repeated launches write nothing -> existing B account with the same e-mail is not linked ->
//   field provenance visible on B's profile pages (teacher and learner).
//
// Every launch goes through A's real mod_lti launch and B's real enrol_lti/auth_lti login; the claims are what A's tool
// configuration (sendname / sendemailaddr) and A's user record make A send. Everything changed on A or B is restored in
// afterAll. Run under the stack lock: flock /tmp/e2e_stack.lock <playwright command>.
const crypto = require('crypto');
const { test, expect } = require('@playwright/test');
const e2e = require(process.env.E2E_LIB || '/home/claude/e2e/lib/e2e');

const seed = e2e.loadSeed();
const { sqlA, sqlB } = e2e;
const user = (key) => e2e.getUser(seed, key);
const login = (page, site, cred) => e2e.login(page, site, cred, seed);
const typeId = seed.lti.platform.type_id;
const esc = (s) => String(s).replace(/'/g, "''");

/** Set one tool type option on A (sendname / sendemailaddr: 1 = always, 0 = never). */
function setTypeConfig(name, value) {
  sqlA(`delete from mdl_lti_types_config where typeid=${typeId} and name='${name}'`);
  sqlA(`insert into mdl_lti_types_config (typeid, name, value) values (${typeId}, '${name}', '${esc(value)}')`);
}

/** Launch the seeded activity as the learner in a fresh browser session; returns {userId, ctx, page} (caller closes). */
async function launch(browser, learner) {
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await login(page, 'a', learner);
  await page.goto(seed.lti_activity.view_url);
  await e2e.waitForLaunch(page,
    new RegExp(`${seed.urls.b.replace(/[.]/g, '\\.')}/course/view\\.php\\?id=${seed.courses.b.id}`));
  const userId = Number(await page.evaluate(() => window.M.cfg.userId));
  return { userId, ctx, page };
}

/** Launch and close the session. */
async function launchOnce(browser, learner) {
  const { userId, ctx } = await launch(browser, learner);
  await ctx.close();
  return userId;
}

/** Stored profile of a B user as "first|last|email". */
const profileB = (id) => sqlB(`select firstname||'|'||lastname||'|'||email from mdl_user where id=${id}`);

test.describe.serial('LTI identity: incomplete, changing and contradicting claims across two sites', () => {
  const learner = user('studentA2');
  const teacher = user('teacherB');
  const original = {};
  let bUserId;
  let sub;
  let iss;
  let placeholderEmail;
  let manualId = 0;

  test.beforeAll(() => {
    original.sendname = sqlA(`select value from mdl_lti_types_config where typeid=${typeId} and name='sendname'`);
    original.sendemail = sqlA(`select value from mdl_lti_types_config where typeid=${typeId} and name='sendemailaddr'`);
    original.container = sqlA(`select launchcontainer from mdl_lti where id=${seed.lti_activity.instance}`);
    original.learnerA = sqlA(`select firstname||'|'||lastname from mdl_user where id=${learner.id}`);
    original.priority = sqlB(`select value from mdl_config_plugins where plugin='local_ltiidentity' and name='claimpriority'`);
    sqlA(`update mdl_lti set launchcontainer=5 where id=${seed.lti_activity.instance}`);
    sqlA(`update mdl_user set firstname='Iris', lastname='Incomplete' where id=${learner.id}`);
    sqlB(`update mdl_config_plugins set value='claimswin' where plugin='local_ltiidentity' and name='claimpriority'`);
    sqlB(`delete from mdl_user where username='e2e_ltiidentity_manual'`);
  });

  test.afterAll(() => {
    setTypeConfig('sendname', original.sendname || '1');
    setTypeConfig('sendemailaddr', original.sendemail || '1');
    sqlA(`update mdl_lti set launchcontainer=${Number(original.container) || 5} where id=${seed.lti_activity.instance}`);
    const [first, last] = (original.learnerA || 'Student|A2').split('|');
    sqlA(`update mdl_user set firstname='${esc(first)}', lastname='${esc(last)}' where id=${learner.id}`);
    if (original.priority) {
      sqlB(`update mdl_config_plugins set value='${esc(original.priority)}' where plugin='local_ltiidentity' and name='claimpriority'`);
    }
    if (manualId) {
      sqlB(`delete from mdl_user where id=${manualId}`);
    }
  });

  test('incomplete claims: name transmitted, e-mail not -> name applied, e-mail stays the placeholder', async ({ browser }) => {
    setTypeConfig('sendname', '1');
    setTypeConfig('sendemailaddr', '0');
    bUserId = await launchOnce(browser, learner);
    expect(bUserId).toBeGreaterThan(2);
    expect(sqlB(`select count(*) from mdl_auth_lti_linked_login where userid=${bUserId}`)).toBe('1');
    // The platform identity as B stored it (a pseudonymising platform plugin may change sub); start from the placeholder
    // state whatever earlier runs left behind, then launch again.
    sub = sqlB(`select sub from mdl_auth_lti_linked_login where userid=${bUserId}`);
    iss = sqlB(`select issuer from mdl_auth_lti_linked_login where userid=${bUserId}`);
    placeholderEmail = `enrol_lti_13_${crypto.createHash('sha1').update(`${iss}_${sub}`).digest('hex')}@example.com`;
    sqlB(`delete from mdl_user_preferences where userid=${bUserId} and name like 'local\\_ltiidentity\\_%'`);
    sqlB(`update mdl_user set auth='lti', firstname='${esc(sub)}', lastname='${esc(iss)}', email='${placeholderEmail}' where id=${bUserId}`);

    expect(await launchOnce(browser, learner)).toBe(bUserId);
    expect(profileB(bUserId)).toBe(`Iris|Incomplete|${placeholderEmail}`);
  });

  test('changing claims: a new name on A reaches B while the field is not locked', async ({ browser }) => {
    sqlA(`update mdl_user set firstname='Ivy' where id=${learner.id}`);
    expect(await launchOnce(browser, learner)).toBe(bUserId);
    expect(profileB(bUserId)).toBe(`Ivy|Incomplete|${placeholderEmail}`);
  });

  test('teacher on B sees the origin "LTI platform" on the course profile, then enters and locks a name', async ({ browser }) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await login(page, 'b', teacher);
    await page.goto(`${seed.urls.b}/user/view.php?id=${bUserId}&course=${seed.courses.b.id}`);
    const main = page.locator('#region-main');
    await expect(main).toContainText('Origin of LTI profile data');
    await expect(main).toContainText('LTI platform (transmitted at a launch)');
    await expect(main).toContainText('LTI platform: not transmitted (automatic placeholder)');

    await page.goto(`${seed.urls.b}/local/ltiidentity/edit.php?id=${seed.courses.b.id}&userid=${bUserId}`);
    await page.fill('#id_firstname', 'Ada');
    await page.fill('#id_lastname', 'Lovelace');
    await Promise.all([page.waitForURL(/ltiidentity\/index\.php/), page.click('#id_submitbutton')]);
    await page.goto(`${seed.urls.b}/user/view.php?id=${bUserId}&course=${seed.courses.b.id}`);
    await expect(main).toContainText('A teacher - locked against LTI launches');
    await ctx.close();
    expect(profileB(bUserId)).toBe(`Ada|Lovelace|${placeholderEmail}`);
  });

  test('contradicting claims: A sends another name -> the locked name on B is kept', async ({ browser }) => {
    sqlA(`update mdl_user set firstname='Augusta', lastname='King' where id=${learner.id}`);
    expect(await launchOnce(browser, learner)).toBe(bUserId);
    expect(profileB(bUserId)).toBe(`Ada|Lovelace|${placeholderEmail}`);
  });

  test('claims withdrawn: A stops sending the name -> nothing is reset', async ({ browser }) => {
    setTypeConfig('sendname', '0');
    expect(await launchOnce(browser, learner)).toBe(bUserId);
    expect(profileB(bUserId)).toBe(`Ada|Lovelace|${placeholderEmail}`);
  });

  test('repeated launches: same account, one binding, the user row is not written', async ({ browser }) => {
    setTypeConfig('sendname', '1');
    sqlB(`update mdl_user set timemodified=1000 where id=${bUserId}`);
    for (let i = 0; i < 2; i++) {
      expect(await launchOnce(browser, learner)).toBe(bUserId);
    }
    expect(sqlB(`select timemodified from mdl_user where id=${bUserId}`)).toBe('1000');
    expect(sqlB(`select count(*) from mdl_auth_lti_linked_login where userid=${bUserId}`)).toBe('1');
    expect(profileB(bUserId)).toBe(`Ada|Lovelace|${placeholderEmail}`);
  });

  test('existing B account with the same e-mail: no linking, no change to it, the LTI account stays separate', async ({ browser }) => {
    const email = sqlA(`select email from mdl_user where id=${learner.id}`);
    const now = Math.floor(Date.now() / 1000);
    manualId = Number(sqlB(`insert into mdl_user (auth, confirmed, mnethostid, username, password, firstname, lastname, email,
      timecreated, timemodified) values ('manual', 1, 1, 'e2e_ltiidentity_manual', '', 'Manual', 'Holder', '${esc(email)}',
      ${now}, ${now}) returning id`).split('\n')[0]);
    expect(manualId).toBeGreaterThan(0);
    setTypeConfig('sendemailaddr', '1');

    expect(await launchOnce(browser, learner)).toBe(bUserId);
    expect(sqlB(`select auth||'|'||firstname||'|'||lastname||'|'||email from mdl_user where id=${manualId}`))
      .toBe(`manual|Manual|Holder|${email}`);
    expect(sqlB(`select count(*) from mdl_auth_lti_linked_login where userid=${manualId}`)).toBe('0');
    // The e-mail field was not locked: the real e-mail claim replaces the placeholder (claims win); names stay locked.
    expect(profileB(bUserId)).toBe(`Ada|Lovelace|${email}`);
  });

  test('the learner sees the origin of the own fields on B', async ({ browser }) => {
    const { ctx, page } = await launch(browser, learner);
    await page.goto(`${seed.urls.b}/user/profile.php`);
    const main = page.locator('#region-main');
    await expect(main).toContainText('Origin of LTI profile data');
    await expect(main).toContainText('A teacher - locked against LTI launches');
    await expect(main).toContainText('LTI platform (transmitted at a launch)');
    await ctx.close();
  });
});
