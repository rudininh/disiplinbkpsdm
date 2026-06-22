import { next } from '@vercel/functions';

const REALM = 'Peta Jabatan';

export const config = {
  matcher: ['/((?!_next|favicon.ico).*)'],
};

function unauthorized(): Response {
  return new Response('Authentication required', {
    status: 401,
    headers: {
      'WWW-Authenticate': `Basic realm="${REALM}", charset="UTF-8"`,
    },
  });
}

function timingSafeEqual(a: string, b: string): boolean {
  if (a.length !== b.length) {
    return false;
  }

  let result = 0;
  for (let index = 0; index < a.length; index += 1) {
    result |= a.charCodeAt(index) ^ b.charCodeAt(index);
  }

  return result === 0;
}

export default function middleware(request: Request): Response {
  const user = process.env.BASIC_AUTH_USER || 'admin';
  const password = process.env.BASIC_AUTH_PASSWORD || 'ubah-password-ini';
  const header = request.headers.get('authorization') || '';

  if (!header.startsWith('Basic ')) {
    return unauthorized();
  }

  try {
    const decoded = atob(header.slice(6));
    const separator = decoded.indexOf(':');
    const givenUser = separator >= 0 ? decoded.slice(0, separator) : '';
    const givenPassword = separator >= 0 ? decoded.slice(separator + 1) : '';

    if (timingSafeEqual(givenUser, user) && timingSafeEqual(givenPassword, password)) {
      return next();
    }
  } catch {
    return unauthorized();
  }

  return unauthorized();
}
