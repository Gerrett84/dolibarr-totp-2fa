# Contributing to TOTP 2FA Module

Thank you for your interest in contributing! This is a community-driven open source project.

## 🤝 How to Contribute

### Reporting Bugs

- Check if the bug has already been reported in [Issues](https://github.com/Gerrett84/dolibarr-totp-2fa/issues)
- If not, create a new issue with:
  - Clear description of the problem
  - Steps to reproduce
  - Expected vs actual behavior
  - Dolibarr version, PHP version
  - Screenshots if applicable

### Suggesting Features

- Open an issue with the `enhancement` label
- Describe the feature and use case
- Discuss implementation approach

### Code Contributions

1. **Fork the repository**
2. **Create a feature branch**
   ```bash
   git checkout -b feature/YourFeature
   ```
3. **Make your changes**
   - Follow existing code style
   - Add comments for complex logic
   - Update documentation if needed
4. **Test your changes**
   - Ensure no regressions
   - Test with Dolibarr 22.0+
5. **Commit with clear messages**
   ```bash
   git commit -m "Add: Description of your changes"
   ```
6. **Push to your fork**
   ```bash
   git push origin feature/YourFeature
   ```
7. **Create a Pull Request**
   - Reference any related issues
   - Describe what changed and why

## 📝 Code Style

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) PHP coding standard
- Use meaningful variable and function names
- Add PHPDoc comments to classes and functions
- Keep functions focused and small

## 🧪 Testing

- Test on a clean Dolibarr installation
- Test with different PHP versions (7.4, 8.0, 8.1, 8.2)
- Test with Google Authenticator and Apple Passwords
- Test edge cases (clock drift, rate limiting, etc.)

## 🔐 Security

If you discover a security vulnerability:
- **DO NOT** open a public issue
- Email the maintainer directly (see GitHub profile)
- Wait for a fix before disclosing publicly

## 📄 License

By contributing, you agree that your contributions will be licensed under GPL-3.0.

## 💬 Questions?

Feel free to open a discussion issue or ask in the PR!
