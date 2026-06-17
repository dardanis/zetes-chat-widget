export function countryLabel(code: string | null | undefined, countries: { code: string; name: string }[]): string {
  if (!code) {
    return '';
  }

  const country = countries.find((item) => item.code === code);

  return country ? `${country.name} (${country.code})` : code;
}
