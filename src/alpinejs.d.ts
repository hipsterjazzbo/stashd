declare module 'alpinejs' {
  interface Alpine {
    data<T>(name: string, callback: (...args: never[]) => T): void
    start(): void
  }

  const Alpine: Alpine

  export default Alpine
}
